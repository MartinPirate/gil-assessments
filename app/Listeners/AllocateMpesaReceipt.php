<?php

namespace App\Listeners;

use App\Events\C2bConfirmationReceived;
use App\Models\MpesaTransaction;
use App\Services\PaymentAllocationService;
use Illuminate\Support\Facades\Log;

/**
 * Settles a captured receipt against the invoice quoted in BillRefNumber.
 */
class AllocateMpesaReceipt
{
    public function __construct(protected PaymentAllocationService $allocations) {}

    public function handle(C2bConfirmationReceived $event): void
    {
        $transaction = $event->transaction;

        // Only freshly captured receipts are matched; re-running for an
        // already-decided one would undo a manual allocation.
        if ($transaction->allocation_status !== MpesaTransaction::ALLOCATION_PENDING) {
            return;
        }

        try {
            $this->allocations->autoAllocate($transaction);
        } catch (\Throwable $e) {
            // The money has already moved, so a reconciliation failure must
            // never discard the receipt. Record it and queue it for a human.
            report($e);

            Log::warning('M-Pesa receipt captured but could not be allocated', [
                'trans_id' => $transaction->trans_id,
                'reason' => $e->getMessage(),
            ]);

            $transaction->update(['allocation_status' => MpesaTransaction::ALLOCATION_UNMATCHED]);
        }
    }
}
