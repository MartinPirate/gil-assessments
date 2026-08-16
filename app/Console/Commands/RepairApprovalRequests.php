<?php

namespace App\Console\Commands;

use App\Models\ApprovalRequest;
use App\Models\Invoice;
use App\Services\ApprovalService;
use App\Support\InvoiceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Finds and repairs invoices stuck in approval limbo.
 *
 * The invariant is that an invoice marked "Pending Approval" always has an
 * open request to decide. `InvoiceWriter` opens both inside one transaction so
 * this cannot drift going forward — but data written before the workflow
 * existed, or restored from an older backup, can violate it. Such a document
 * shows as pending forever with nothing in the queue, which nobody notices
 * because the approvals list is filtered to Pending by default.
 */
class RepairApprovalRequests extends Command
{
    protected $signature = 'invoices:repair-approvals
        {--dry : Report what would change without writing}';

    protected $description = 'Open approval requests for invoices stuck as Pending Approval with no request';

    public function handle(ApprovalService $approvals): int
    {
        $dryRun = (bool) $this->option('dry');

        $orphans = Invoice::query()
            ->where('status', Invoice::STATUS_PENDING_APPROVAL)
            ->whereDoesntHave('approvalRequests', fn ($q) => $q->where('status', ApprovalRequest::STATUS_PENDING))
            ->get();

        if ($orphans->isEmpty()) {
            $this->components->info('No stuck invoices. Every pending document has an open request.');

            return self::SUCCESS;
        }

        $this->components->warn("{$orphans->count()} invoice(s) are Pending Approval with no open request:");

        foreach ($orphans as $invoice) {
            // A document total of zero means the figure predates the column;
            // recompute from the lines so the request carries a real amount.
            $amount = (float) $invoice->document_total;

            if ($amount <= 0) {
                $amount = $this->recomputeTotal($invoice);
            }

            $this->components->twoColumnDetail(
                "  {$invoice->document_number} — {$invoice->customer_name}",
                'KES '.number_format($amount, 2),
            );

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($invoice, $amount, $approvals) {
                // Repair the stale total before the request copies it.
                if ((float) $invoice->document_total <= 0 && $amount > 0) {
                    $invoice->update([
                        'document_total' => $amount,
                        'balance_due' => max(0, $amount - (float) $invoice->applied_amount),
                    ]);
                }

                $approvals->requestIfNeeded($invoice, $invoice->created_by ?? $invoice->owner_id);
            });
        }

        if ($dryRun) {
            $this->newLine();
            $this->components->info('Dry run — nothing was written.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->info("Opened {$orphans->count()} approval request(s). They now appear in the queue.");

        return self::SUCCESS;
    }

    /**
     * Rebuild a document total from its own lines.
     */
    protected function recomputeTotal(Invoice $invoice): float
    {
        $lines = $invoice->lines->map(fn ($line) => [
            'item_no' => $line->item?->item_no,
            'quantity' => (float) $line->quantity,
            'price_before_discount' => (float) $line->price_before_discount,
            'discount_percent' => (float) $line->discount_percent,
            'vat_rate' => (float) $line->vat_rate,
        ])->all();

        $totals = InvoiceCalculator::documentTotals(
            $lines,
            (float) $invoice->discount_percent,
            (float) $invoice->freight,
            (float) $invoice->total_down_payment,
            (float) $invoice->applied_amount,
            (bool) $invoice->rounding_enabled,
        );

        return $totals['document_total'];
    }
}
