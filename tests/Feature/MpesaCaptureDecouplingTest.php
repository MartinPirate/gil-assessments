<?php

namespace Tests\Feature;

use App\Events\C2bConfirmationReceived;
use App\Models\MpesaTransaction;
use App\Services\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Capture must be independent of reconciliation.
 *
 * Recording that money arrived is the part that can never fail; deciding which
 * invoice it settles is business logic that may legitimately break.
 */
class MpesaCaptureDecouplingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    protected function payload(): array
    {
        return [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'RKTQDM7W6S',
            'TransAmount' => '1500.00',
            'BillRefNumber' => 'IN-1',
            'MSISDN' => '254708374149',
        ];
    }

    public function test_a_confirmation_announces_itself(): void
    {
        Event::fake([C2bConfirmationReceived::class]);

        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload())->assertOk();

        Event::assertDispatched(
            C2bConfirmationReceived::class,
            fn (C2bConfirmationReceived $e) => $e->transaction->trans_id === 'RKTQDM7W6S'
                && $e->payload['BillRefNumber'] === 'IN-1',
        );
    }

    /**
     * A validation callback is a question, not money — nothing to reconcile.
     */
    public function test_a_validation_callback_announces_nothing(): void
    {
        Event::fake([C2bConfirmationReceived::class]);

        $this->postJson('/api/mpesa/c2b/validation', $this->payload())->assertOk();

        Event::assertNotDispatched(C2bConfirmationReceived::class);
    }

    /**
     * The whole point of the split: if reconciliation blows up, the receipt is
     * still on record and queued for a human rather than lost.
     */
    public function test_the_receipt_survives_a_failing_allocator(): void
    {
        $this->mock(PaymentAllocationService::class, function ($mock) {
            $mock->shouldReceive('autoAllocate')
                ->andThrow(new \RuntimeException('reconciliation is broken'));
        });

        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload())
            ->assertOk()
            ->assertJsonPath('ResultCode', 0);

        $transaction = MpesaTransaction::firstOrFail();

        $this->assertSame('RKTQDM7W6S', $transaction->trans_id);
        $this->assertSame('1500.00', $transaction->trans_amount);
        $this->assertSame(MpesaTransaction::ALLOCATION_UNMATCHED, $transaction->allocation_status);
    }
}
