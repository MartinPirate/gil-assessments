<?php

namespace App\Services;

use App\Enums\OrderStage;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Applies M-Pesa receipts to invoices.
 *
 * Without this the C2B endpoint is a write-only log: money arrives and nobody
 * knows which invoice it settled. The customer types the invoice number into
 * the M-Pesa account field, so BillRefNumber is the link.
 */
class PaymentAllocationService
{
    public function __construct(protected OrderLifecycleService $lifecycle) {}

    /**
     * Try to settle a receipt against the invoice named in BillRefNumber.
     *
     * Returns the allocation, or null when nothing could be matched — an
     * unmatched receipt is a normal business situation (wrong reference typed),
     * not an error, and is queued for a human instead.
     */
    public function autoAllocate(MpesaTransaction $transaction): ?PaymentAllocation
    {
        if ($transaction->callback_type !== MpesaTransaction::TYPE_CONFIRMATION) {
            return null;   // a validation callback is not money received
        }

        $amount = (float) $transaction->trans_amount;

        if ($amount <= 0) {
            $this->markUnmatched($transaction);

            return null;
        }

        $invoice = $this->findInvoiceByReference($transaction->bill_ref_number);

        if (! $invoice) {
            $this->markUnmatched($transaction);

            return null;
        }

        return $this->allocate($transaction, $invoice, $amount, PaymentAllocation::MATCHED_AUTO, null);
    }

    /**
     * Resolve "INV-12", "IN-00000012", "12" or "IN12" to a document.
     *
     * Customers type this by hand into the M-Pesa account field, so the
     * matching has to tolerate the obvious variations while still being exact
     * about which document it lands on.
     */
    public function findInvoiceByReference(?string $reference): ?Invoice
    {
        $reference = trim((string) $reference);

        if ($reference === '') {
            return null;
        }

        // Everything after the last non-digit run is the document number.
        if (! preg_match('/(\d+)\s*$/', $reference, $matches)) {
            return null;
        }

        $docNum = (int) $matches[1];

        if ($docNum <= 0) {
            return null;
        }

        $query = Invoice::query()->outstanding()->where('doc_num', $docNum);

        // If a series prefix was given, honour it; otherwise take the only
        // outstanding match, and refuse to guess when there is more than one.
        if (preg_match('/^([A-Za-z]{1,8})/', $reference, $seriesMatch)) {
            $series = strtoupper($seriesMatch[1]);

            $withSeries = (clone $query)->where('series', $series)->first();

            if ($withSeries) {
                return $withSeries;
            }
        }

        return $query->count() === 1 ? $query->first() : null;
    }

    /**
     * Write an allocation and update both sides atomically.
     *
     * The invoice row is locked because two callbacks quoting the same
     * reference could otherwise both read the same balance and over-apply.
     */
    public function allocate(
        MpesaTransaction $transaction,
        Invoice $invoice,
        float $amount,
        string $matchedBy = PaymentAllocation::MATCHED_MANUAL,
        ?int $userId = null,
    ): PaymentAllocation {
        return DB::transaction(function () use ($transaction, $invoice, $amount, $matchedBy, $userId) {
            $lockedInvoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $lockedTxn = MpesaTransaction::query()->whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();

            if (! $lockedInvoice->acceptsPayment()) {
                throw ValidationException::withMessages([
                    'invoice' => "Invoice {$lockedInvoice->document_number} cannot accept a payment (status {$lockedInvoice->status}, balance {$lockedInvoice->balance_due}).",
                ]);
            }

            $unallocated = round((float) $lockedTxn->trans_amount - (float) $lockedTxn->allocated_amount, 3);

            if ($amount > $unallocated + 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => "Only {$unallocated} of this receipt is unallocated.",
                ]);
            }

            // Never apply more than the invoice still owes; the remainder stays
            // on the receipt to be allocated elsewhere or refunded.
            $applied = round(min($amount, (float) $lockedInvoice->balance_due), 3);

            if ($applied <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'There is nothing left to allocate.',
                ]);
            }

            $allocation = PaymentAllocation::updateOrCreate(
                [
                    'mpesa_transaction_id' => $lockedTxn->getKey(),
                    'invoice_id' => $lockedInvoice->getKey(),
                ],
                [
                    'amount' => $applied,
                    'matched_by' => $matchedBy,
                    'allocated_by' => $userId,
                    'allocated_at' => now(),
                ],
            );

            $this->refreshInvoiceBalance($lockedInvoice);
            $this->refreshTransactionAllocation($lockedTxn);

            Log::info('M-Pesa receipt allocated', [
                'trans_id' => $lockedTxn->trans_id,
                'invoice' => $lockedInvoice->document_number,
                'amount' => $applied,
                'matched_by' => $matchedBy,
            ]);

            return $allocation;
        });
    }

    /**
     * Remove an allocation and restore both balances.
     */
    public function unallocate(PaymentAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation) {
            $invoice = Invoice::query()->whereKey($allocation->invoice_id)->lockForUpdate()->firstOrFail();
            $transaction = MpesaTransaction::query()->whereKey($allocation->mpesa_transaction_id)->lockForUpdate()->firstOrFail();

            $allocation->delete();

            $this->refreshInvoiceBalance($invoice);
            $this->refreshTransactionAllocation($transaction);
        });
    }

    /**
     * Recompute applied/balance from the allocations themselves rather than
     * incrementing a running total, so the figures cannot drift out of step
     * with the rows that justify them.
     */
    protected function refreshInvoiceBalance(Invoice $invoice): void
    {
        $applied = (float) $invoice->allocations()->sum('amount');
        $balance = round(max(0, (float) $invoice->document_total - $applied), 3);

        $invoice->update([
            'applied_amount' => round($applied, 3),
            'balance_due' => $balance,
            'status' => $this->statusAfterPayment($invoice, $balance),
        ]);

        /*
         * Settled in full — the order has reached "paid".
         *
         * Recorded here rather than in allocate() because this is the one place
         * that knows the balance actually reached zero, and it is also reached
         * by unallocate(): a receipt taken back off a document leaves a balance
         * again, and the stage is simply not recorded.
         *
         * There is no user in a Safaricom callback, so the causer is left to
         * resolve to whoever is authenticated, which is nobody.
         */
        if ($balance <= 0) {
            $this->lifecycle->record(
                $invoice,
                OrderStage::Paid,
                note: 'Settled in full — KES '.number_format($applied, 2).' applied.',
                meta: ['applied_amount' => round($applied, 3)],
            );
        }
    }

    /**
     * A fully paid document closes, but an unapproved one must not be quietly
     * promoted past its approval step just because it was paid.
     */
    protected function statusAfterPayment(Invoice $invoice, float $balance): string
    {
        if (in_array($invoice->status, [
            Invoice::STATUS_PENDING_APPROVAL,
            Invoice::STATUS_REJECTED,
            Invoice::STATUS_CANCELLED,
        ], true)) {
            return $invoice->status;
        }

        return $balance <= 0 ? Invoice::STATUS_CLOSED : Invoice::STATUS_OPEN;
    }

    protected function refreshTransactionAllocation(MpesaTransaction $transaction): void
    {
        $allocated = round((float) $transaction->allocations()->sum('amount'), 3);
        $received = (float) $transaction->trans_amount;

        $transaction->update([
            'allocated_amount' => $allocated,
            'allocation_status' => match (true) {
                $allocated <= 0 => MpesaTransaction::ALLOCATION_UNMATCHED,
                $allocated + 0.0001 < $received => MpesaTransaction::ALLOCATION_PARTIAL,
                default => MpesaTransaction::ALLOCATION_MATCHED,
            },
        ]);
    }

    protected function markUnmatched(MpesaTransaction $transaction): void
    {
        $transaction->update([
            'allocation_status' => MpesaTransaction::ALLOCATION_UNMATCHED,
        ]);
    }
}
