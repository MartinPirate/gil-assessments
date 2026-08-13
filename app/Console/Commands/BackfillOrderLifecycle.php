<?php

namespace App\Console\Commands;

use App\Enums\OrderStage;
use App\Models\Invoice;
use App\Models\Trip;
use App\Services\OrderLifecycleService;
use Illuminate\Console\Command;

/**
 * Reconstructs the order lifecycle for documents raised before it existed.
 *
 * Stages are recorded as they happen, which means every invoice written before
 * this feature shipped has an empty timeline — the document is plainly there
 * and settled, and the lifecycle claims nothing ever happened to it.
 *
 * What can be recovered honestly is recovered:
 *
 *   Placed      every posted document, at its posting date
 *   Paid        anything with no balance left
 *   Dispatched  a linked trip that departed, at its departure time
 *   Delivered   a linked trip that arrived, at its arrival time
 *
 * Approval and rating are deliberately not inferred. An approved document
 * records who decided it and when, and a rating is a customer's opinion —
 * inventing either would put a name or a score against something that never
 * happened. A document that only ever reached "placed" is the truthful answer.
 */
class BackfillOrderLifecycle extends Command
{
    protected $signature = 'orders:backfill-lifecycle
        {--dry : Report what would be written without writing it}';

    protected $description = 'Reconstruct order lifecycle stages for invoices raised before the feature existed';

    public function handle(OrderLifecycleService $lifecycle): int
    {
        $dryRun = (bool) $this->option('dry');

        $invoices = Invoice::query()
            ->posted()
            ->with('trips')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->components->info('No posted invoices to backfill.');

            return self::SUCCESS;
        }

        $written = 0;
        $touched = 0;

        foreach ($invoices as $invoice) {
            $stages = $this->stagesFor($invoice);
            $missing = [];

            foreach ($stages as [$stage, $occurredAt, $note]) {
                if ($lifecycle->hasReached($invoice, $stage)) {
                    continue;
                }

                $missing[] = $stage->label();

                if (! $dryRun) {
                    // Null causer on purpose: nobody alive performed these, and
                    // attributing them to whoever ran the command would be a lie
                    // in the audit trail.
                    $lifecycle->record($invoice, $stage, $occurredAt, null, $note);
                    $written++;
                }
            }

            if ($missing !== []) {
                $touched++;
                $this->line("  {$invoice->document_number}: ".implode(', ', $missing));
            }
        }

        if ($touched === 0) {
            $this->components->info('Every posted invoice already has its lifecycle.');

            return self::SUCCESS;
        }

        $dryRun
            ? $this->components->warn("{$touched} invoice(s) would be backfilled. Re-run without --dry to write.")
            : $this->components->info("Backfilled {$written} stage(s) across {$touched} invoice(s).");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{OrderStage, \DateTimeInterface|null, string}>
     */
    protected function stagesFor(Invoice $invoice): array
    {
        $stages = [[
            OrderStage::Placed,
            $invoice->posting_date ?? $invoice->created_at,
            "Invoice {$invoice->document_number} raised.",
        ]];

        if ((float) $invoice->balance_due <= 0) {
            $stages[] = [
                OrderStage::Paid,
                $invoice->updated_at,
                'Settled in full — reconstructed from the document balance.',
            ];
        }

        foreach ($invoice->trips as $trip) {
            if ($trip->departed_at !== null) {
                $stages[] = [
                    OrderStage::Dispatched,
                    $trip->departed_at,
                    "Trip {$trip->reference} departed on {$trip->route_name}.",
                ];
            }

            if ($trip->arrived_at !== null && $trip->status === Trip::STATUS_COMPLETED) {
                $stages[] = [
                    OrderStage::Delivered,
                    $trip->arrived_at,
                    "Trip {$trip->reference} arrived.",
                ];
            }
        }

        return $stages;
    }
}
