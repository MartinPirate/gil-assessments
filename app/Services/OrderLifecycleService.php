<?php

namespace App\Services;

use App\Enums\OrderStage;
use App\Models\Invoice;
use App\Models\OrderStageEvent;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Records the milestones an order passes.
 *
 * Every caller here is a side effect of something else succeeding — an invoice
 * posting, a receipt settling, a driver arriving. None of them may fail the
 * thing that triggered them, and all of them can fire more than once for the
 * same order: Safaricom retries callbacks, and a trip can be saved twice.
 */
class OrderLifecycleService
{
    /**
     * Record a stage, once.
     *
     * Returns the new event, or null when the order had already reached this
     * stage — callers treat null as "nothing to do", not as a failure.
     *
     * @param  array<string, mixed>  $meta
     */
    public function record(
        Invoice $invoice,
        OrderStage $stage,
        ?DateTimeInterface $occurredAt = null,
        ?User $causer = null,
        ?string $note = null,
        array $meta = [],
    ): ?OrderStageEvent {
        $causer ??= Auth::user();

        try {
            /*
             * Straight to the insert, with no "does it exist yet" read in
             * front of it. Two callbacks arriving together would both pass
             * such a check and both write, which is exactly the duplicate the
             * unique index on (invoice_id, stage) exists to refuse. Letting
             * the database answer makes the race harmless.
             */
            return OrderStageEvent::create([
                'invoice_id' => $invoice->getKey(),
                'stage' => $stage,
                'occurred_at' => $occurredAt ?? now(),
                'causer_id' => $causer?->getKey(),
                // Denormalised so the timeline still names who acted after the
                // user account is deleted and the foreign key nulls out.
                'causer_name' => $causer?->name,
                'note' => $note,
                'meta' => $meta === [] ? null : $meta,
            ]);
        } catch (UniqueConstraintViolationException) {
            // The order was already at this stage. That is the normal outcome
            // of a retry, so it is not worth a warning.
            return null;
        } catch (\Throwable $e) {
            /*
             * The lifecycle is a record of work, not the work itself. If it
             * cannot be written, the payment or delivery that triggered it
             * must still stand — so this is logged and swallowed rather than
             * rolled back onto the caller.
             */
            Log::warning('Could not record order stage', [
                'invoice_id' => $invoice->getKey(),
                'stage' => $stage->value,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The stages an order has reached, oldest first.
     *
     * @return Collection<int, OrderStageEvent>
     */
    public function stages(Invoice $invoice): Collection
    {
        return OrderStageEvent::query()
            ->where('invoice_id', $invoice->getKey())
            ->chronological()
            ->get();
    }

    public function hasReached(Invoice $invoice, OrderStage $stage): bool
    {
        return OrderStageEvent::query()
            ->where('invoice_id', $invoice->getKey())
            ->where('stage', $stage->value)
            ->exists();
    }

    /**
     * The furthest point on the standard track this order has reached, used to
     * draw the progress bar. Stages off the track (approval, cancellation) do
     * not advance it.
     */
    public function currentPosition(Invoice $invoice): int
    {
        $reached = OrderStageEvent::query()
            ->where('invoice_id', $invoice->getKey())
            ->pluck('stage');

        return $reached
            ->map(fn (OrderStage $stage): int => in_array($stage, OrderStage::track(), true)
                ? (int) $stage->position()
                : 0)
            ->max() ?? 0;
    }
}
