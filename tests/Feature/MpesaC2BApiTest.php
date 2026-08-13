<?php

namespace Tests\Feature;

use App\Models\MpesaTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Task 3 — the M-Pesa C2B callback API.
 */
class MpesaC2BApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A complete Daraja C2B confirmation body.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'TransactionType' => 'Pay Bill',
            'TransID' => 'RKTQDM7W6S',
            'TransTime' => '20260810180000',
            'TransAmount' => '1500.00',
            'BusinessShortCode' => '600984',
            'BillRefNumber' => 'INV-1',
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '49197.00',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254708374149',
            'FirstName' => 'John',
            'MiddleName' => 'Doe',
            'LastName' => 'Mwangi',
        ], $overrides);
    }

    public function test_a_confirmation_callback_is_stored_field_by_field(): void
    {
        $response = $this->postJson('/api/mpesa/c2b/confirmation', $this->payload());

        $response->assertOk()
            ->assertJsonPath('ResultCode', 0)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fields.TransID', 'RKTQDM7W6S')
            ->assertJsonPath('data.fields.TransAmount', '1500.00')
            ->assertJsonPath('data.payer_name', 'John Doe Mwangi');

        $transaction = MpesaTransaction::firstOrFail();

        $this->assertSame('Pay Bill', $transaction->transaction_type);
        $this->assertSame('RKTQDM7W6S', $transaction->trans_id);
        $this->assertSame('20260810180000', $transaction->trans_time);
        $this->assertSame('1500.00', $transaction->trans_amount);
        $this->assertSame('600984', $transaction->business_short_code);
        $this->assertSame('INV-1', $transaction->bill_ref_number);
        $this->assertSame('49197.00', $transaction->org_account_balance);
        $this->assertSame('254708374149', $transaction->msisdn);
        $this->assertSame('John', $transaction->first_name);
        $this->assertSame('Doe', $transaction->middle_name);
        $this->assertSame('Mwangi', $transaction->last_name);
        $this->assertSame(MpesaTransaction::TYPE_CONFIRMATION, $transaction->callback_type);
    }

    public function test_the_raw_payload_is_retained(): void
    {
        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload());

        $this->assertSame('RKTQDM7W6S', MpesaTransaction::firstOrFail()->raw_payload['TransID']);
    }

    /**
     * Safaricom retries until it gets a success, so the same TransID can
     * legitimately arrive twice. It must not become two payments.
     */
    public function test_a_repeated_callback_is_idempotent(): void
    {
        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload())->assertOk();
        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload())->assertOk();

        $this->assertSame(1, MpesaTransaction::count());
    }

    public function test_validation_and_confirmation_are_stored_separately(): void
    {
        $this->postJson('/api/mpesa/c2b/validation', $this->payload())->assertOk();
        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload())->assertOk();

        $this->assertSame(2, MpesaTransaction::count());
        $this->assertSame(1, MpesaTransaction::where('callback_type', MpesaTransaction::TYPE_VALIDATION)->count());
        $this->assertSame(1, MpesaTransaction::where('callback_type', MpesaTransaction::TYPE_CONFIRMATION)->count());
    }

    public function test_numeric_json_values_are_accepted_and_stored_as_strings(): void
    {
        // Daraja sends TransAmount unquoted in some environments.
        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload([
            'TransAmount' => 1500.5,
            'BusinessShortCode' => 600984,
        ]))->assertOk();

        $transaction = MpesaTransaction::firstOrFail();

        $this->assertSame('1500.5', $transaction->trans_amount);
        $this->assertSame('600984', $transaction->business_short_code);
    }

    public function test_empty_optional_fields_are_stored_as_null_and_reported(): void
    {
        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload())
            ->assertJsonPath('data.missing_fields', ['InvoiceNumber', 'ThirdPartyTransID']);

        $this->assertNull(MpesaTransaction::firstOrFail()->invoice_number);
    }

    public function test_unexpected_extra_fields_are_reported_but_not_fatal(): void
    {
        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload(['SomeNewField' => 'x']))
            ->assertOk()
            ->assertJsonPath('data.unmapped_fields', ['SomeNewField']);
    }

    public function test_a_payload_without_a_trans_id_is_rejected_in_safaricom_format(): void
    {
        $payload = $this->payload();
        unset($payload['TransID']);

        $this->postJson('/api/mpesa/c2b/confirmation', $payload)
            ->assertStatus(422)
            // Not Laravel's default body: Safaricom's parser needs ResultCode.
            ->assertJsonPath('ResultCode', 1)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['TransID']]);

        $this->assertSame(0, MpesaTransaction::count());
    }

    public function test_field_names_keep_safaricom_casing_in_errors(): void
    {
        $payload = $this->payload();
        unset($payload['TransID']);

        $response = $this->postJson('/api/mpesa/c2b/confirmation', $payload);

        $this->assertStringContainsString('TransID', $response->json('errors.TransID.0'));
    }

    public function test_the_transaction_time_is_exposed_as_a_date(): void
    {
        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload());

        $this->assertSame(
            '2026-08-10 18:00:00',
            MpesaTransaction::firstOrFail()->transacted_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_a_malformed_transaction_time_does_not_break_the_accessor(): void
    {
        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload(['TransTime' => 'not-a-time']))
            ->assertOk();

        $this->assertNull(MpesaTransaction::firstOrFail()->transacted_at);
    }

    public function test_callbacks_from_unlisted_ips_are_rejected_when_an_allow_list_is_set(): void
    {
        Config::set('services.mpesa.allowed_ips', '196.201.214.200');

        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('ResultCode', 1);

        $this->assertSame(0, MpesaTransaction::count());
    }

    public function test_an_empty_allow_list_accepts_any_ip(): void
    {
        Config::set('services.mpesa.allowed_ips', '');

        $this->postJson('/api/mpesa/c2b/confirmation', $this->payload())->assertOk();

        $this->assertSame(1, MpesaTransaction::count());
    }
}
