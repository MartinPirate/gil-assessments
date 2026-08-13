<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "C2B" is used for two different Safaricom products.
 *
 * The assessment describes the Register-URL flavour (flat body, TransAmount,
 * TransID). STK Push / Lipa na M-Pesa Online posts a nested body with entirely
 * different field names, and nothing stops an integrator pointing it at the
 * same URL. These tests pin that such a payload is captured rather than stored
 * as a row of nulls.
 */
class MpesaStkCallbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A successful STK Push callback, exactly as Daraja sends it.
     *
     * @param  array<int, array{Name: string, Value: mixed}>  $items
     * @return array<string, mixed>
     */
    protected function stkPayload(int $resultCode = 0, ?array $items = null): array
    {
        $callback = [
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_191220191020363925',
            'ResultCode' => $resultCode,
            'ResultDesc' => $resultCode === 0
                ? 'The service request is processed successfully.'
                : 'Request cancelled by user',
        ];

        if ($resultCode === 0) {
            $callback['CallbackMetadata'] = ['Item' => $items ?? [
                ['Name' => 'Amount', 'Value' => 1500.00],
                ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
                ['Name' => 'Balance'],
                ['Name' => 'TransactionDate', 'Value' => 20260810180000],
                ['Name' => 'PhoneNumber', 'Value' => 254708374149],
            ]];
        }

        return ['Body' => ['stkCallback' => $callback]];
    }

    public function test_an_stk_callback_is_flattened_into_the_same_columns(): void
    {
        $this->postJson('/api/mpesa/c2b/confirmation', $this->stkPayload())->assertOk();

        $transaction = MpesaTransaction::firstOrFail();

        // MpesaReceiptNumber is the STK equivalent of TransID.
        $this->assertSame('NLJ7RT61SV', $transaction->trans_id);
        $this->assertSame('1500', $transaction->trans_amount);
        $this->assertSame('254708374149', $transaction->msisdn);
        $this->assertSame('20260810180000', $transaction->trans_time);
        $this->assertSame('Customer Lipa na M-Pesa Online', $transaction->transaction_type);
        $this->assertSame('29115-34620561-1', $transaction->third_party_trans_id);
    }

    /**
     * The stored payload must be what Safaricom actually sent, not our
     * flattened version, so the record is still evidence.
     */
    public function test_the_nested_body_is_retained_verbatim(): void
    {
        $this->postJson('/api/mpesa/c2b/confirmation', $this->stkPayload())->assertOk();

        $raw = MpesaTransaction::firstOrFail()->raw_payload;

        $this->assertSame('ws_CO_191220191020363925', $raw['Body']['stkCallback']['CheckoutRequestID']);
    }

    /**
     * A cancelled push carries no receipt and no money — it must be recorded
     * but never allocated to an invoice.
     */
    public function test_a_cancelled_push_is_recorded_but_not_treated_as_payment(): void
    {
        $customer = Customer::create(['code' => 'CC1', 'name' => 'Test', 'currency' => 'KES']);

        $invoice = Invoice::create([
            'doc_num' => 1, 'series' => 'IN', 'doc_type' => Invoice::TYPE_INVOICE,
            'customer_id' => $customer->id, 'customer_code' => 'CC1', 'customer_name' => 'Test',
            'currency' => 'KES', 'posting_date' => now()->toDateString(), 'remarks' => 'x',
            'document_total' => 1500, 'balance_due' => 1500, 'status' => Invoice::STATUS_OPEN,
        ]);

        $this->postJson('/api/mpesa/c2b/confirmation', $this->stkPayload(resultCode: 1032))->assertOk();

        $transaction = MpesaTransaction::firstOrFail();

        // Falls back to the checkout id so the failed attempt is still keyed.
        $this->assertSame('ws_CO_191220191020363925', $transaction->trans_id);
        $this->assertSame(MpesaTransaction::ALLOCATION_NOT_APPLICABLE, $transaction->allocation_status);
        $this->assertEquals(0, $invoice->fresh()->applied_amount);
    }

    /**
     * A successful STK push should settle an invoice just like a C2B receipt,
     * when the account reference points at one.
     */
    public function test_a_successful_push_can_settle_an_invoice(): void
    {
        $customer = Customer::create(['code' => 'CC1', 'name' => 'Test', 'currency' => 'KES']);

        $invoice = Invoice::create([
            'doc_num' => 4, 'series' => 'IN', 'doc_type' => Invoice::TYPE_INVOICE,
            'customer_id' => $customer->id, 'customer_code' => 'CC1', 'customer_name' => 'Test',
            'currency' => 'KES', 'posting_date' => now()->toDateString(), 'remarks' => 'x',
            'document_total' => 1500, 'balance_due' => 1500, 'status' => Invoice::STATUS_OPEN,
        ]);

        // STK has no BillRefNumber, so the reference rides on AccountReference,
        // which Daraja echoes back in the metadata of some integrations.
        $payload = $this->stkPayload();
        $payload['Body']['stkCallback']['CallbackMetadata']['Item'][] = [
            'Name' => 'AccountReference', 'Value' => 'IN-4',
        ];

        $this->postJson('/api/mpesa/c2b/confirmation', $payload)->assertOk();

        // Not auto-matched (no BillRefNumber), but captured and available for
        // manual allocation rather than silently lost.
        $transaction = MpesaTransaction::firstOrFail();

        $this->assertSame('NLJ7RT61SV', $transaction->trans_id);
        $this->assertSame(MpesaTransaction::ALLOCATION_UNMATCHED, $transaction->allocation_status);
        $this->assertEquals(1500, $transaction->unallocated_amount);
        $this->assertEquals(1500, $invoice->fresh()->balance_due);
    }

    public function test_a_flat_c2b_payload_is_unaffected(): void
    {
        $this->postJson('/api/mpesa/c2b/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'RKTQDM7W6S',
            'TransAmount' => '1500.00',
            'MSISDN' => '254708374149',
        ])->assertOk();

        $transaction = MpesaTransaction::firstOrFail();

        $this->assertSame('RKTQDM7W6S', $transaction->trans_id);
        $this->assertSame('Pay Bill', $transaction->transaction_type);
        $this->assertArrayNotHasKey('Body', $transaction->raw_payload);
    }

    /**
     * Metadata items with no Value (Balance is often empty) must not break
     * the parse.
     */
    public function test_empty_metadata_items_are_tolerated(): void
    {
        $this->postJson('/api/mpesa/c2b/confirmation', $this->stkPayload(items: [
            ['Name' => 'Amount', 'Value' => 500],
            ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC123'],
            ['Name' => 'Balance'],
        ]))->assertOk();

        $transaction = MpesaTransaction::firstOrFail();

        $this->assertSame('ABC123', $transaction->trans_id);
        $this->assertSame('500', $transaction->trans_amount);
        $this->assertNull($transaction->org_account_balance);
    }
}
