<?php

namespace App\Events;

use App\Models\MpesaTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once an M-Pesa receipt has been captured.
 *
 * Capture and reconciliation are deliberately separated: recording the money is
 * the part that must never fail, while working out which invoice it settles is
 * business logic that may legitimately fail or change. A listener also gives a
 * natural place to queue the work later without touching the endpoint.
 */
class C2bConfirmationReceived
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  the body exactly as Safaricom sent it
     */
    public function __construct(
        public MpesaTransaction $transaction,
        public array $payload = [],
    ) {}
}
