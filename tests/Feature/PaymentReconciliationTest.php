<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Matching M-Pesa receipts to invoices.
 */
class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'code' => 'CC00001', 'name' => 'Naivas Supermarket Ltd', 'currency' => 'KES',
        ]);
    }

    protected function invoice(float $total = 10000, int $docNum = 1, string $series = 'IN', string $status = Invoice::STATUS_OPEN): Invoice
    {
        return Invoice::create([
            'doc_num' => $docNum,
            'series' => $series,
            'doc_type' => Invoice::TYPE_INVOICE,
            'customer_id' => $this->customer->id,
            'customer_code' => $this->customer->code,
            'customer_name' => $this->customer->name,
            'currency' => 'KES',
            'posting_date' => now()->toDateString(),
            'remarks' => 'Test',
            'total_before_discount' => $total,
            'total_after_discount' => $total,
            'document_total' => $total,
            'balance_due' => $total,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sendReceipt(array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/mpesa/c2b/confirmation', array_merge([
            'TransactionType' => 'Pay Bill',
            'TransID' => 'RCT'.fake()->unique()->numerify('######'),
            'TransAmount' => '10000.00',
            'BillRefNumber' => 'IN-1',
            'MSISDN' => '254722345678',
            'FirstName' => 'Jane',
        ], $payload));
    }

    public function test_a_receipt_is_matched_to_the_invoice_in_the_reference(): void
    {
        $invoice = $this->invoice(10000);

        $this->sendReceipt()->assertOk();

        $invoice->refresh();

        $this->assertEquals(10000, $invoice->applied_amount);
        $this->assertEquals(0, $invoice->balance_due);
        $this->assertSame(Invoice::STATUS_CLOSED, $invoice->status);
        $this->assertSame(MpesaTransaction::ALLOCATION_MATCHED, MpesaTransaction::first()->allocation_status);
    }

    public function test_a_partial_payment_leaves_a_balance(): void
    {
        $invoice = $this->invoice(10000);

        $this->sendReceipt(['TransAmount' => '4000.00'])->assertOk();

        $invoice->refresh();

        $this->assertEquals(4000, $invoice->applied_amount);
        $this->assertEquals(6000, $invoice->balance_due);
        $this->assertSame(Invoice::STATUS_OPEN, $invoice->status);
    }

    /**
     * The excess must stay on the receipt rather than pushing the invoice
     * into a negative balance.
     */
    public function test_an_overpayment_is_only_applied_up_to_the_balance(): void
    {
        $invoice = $this->invoice(10000);

        $this->sendReceipt(['TransAmount' => '15000.00'])->assertOk();

        $invoice->refresh();
        $transaction = MpesaTransaction::first();

        $this->assertEquals(10000, $invoice->applied_amount);
        $this->assertEquals(0, $invoice->balance_due);
        $this->assertEquals(10000, $transaction->allocated_amount);
        $this->assertEquals(5000, $transaction->unallocated_amount);
        $this->assertSame(MpesaTransaction::ALLOCATION_PARTIAL, $transaction->allocation_status);
    }

    public function test_an_unknown_reference_is_queued_as_unmatched(): void
    {
        $invoice = $this->invoice(10000);

        $this->sendReceipt(['BillRefNumber' => 'GARBAGE-999'])->assertOk();

        $this->assertEquals(0, $invoice->fresh()->applied_amount);
        $this->assertSame(MpesaTransaction::ALLOCATION_UNMATCHED, MpesaTransaction::first()->allocation_status);
    }

    /**
     * Customers type the reference by hand, so the obvious variants have to
     * resolve to the same document.
     */
    public function test_reference_formats_are_tolerated(): void
    {
        $invoice = $this->invoice(10000, docNum: 7);
        $service = app(PaymentAllocationService::class);

        foreach (['IN-7', 'INV-7', 'IN7', '7', ' in 7 '] as $reference) {
            $this->assertTrue(
                $service->findInvoiceByReference($reference)?->is($invoice) ?? false,
                "Reference [{$reference}] should resolve to the invoice.",
            );
        }
    }

    public function test_an_ambiguous_bare_number_is_not_guessed(): void
    {
        // Same number in two series: guessing could pay the wrong document.
        $this->invoice(10000, docNum: 5, series: 'IN');
        $this->invoice(10000, docNum: 5, series: 'CR');

        $this->assertNull(app(PaymentAllocationService::class)->findInvoiceByReference('5'));
    }

    public function test_an_explicit_series_resolves_an_otherwise_ambiguous_number(): void
    {
        $in = $this->invoice(10000, docNum: 5, series: 'IN');
        $this->invoice(10000, docNum: 5, series: 'CR');

        $this->assertTrue(
            app(PaymentAllocationService::class)->findInvoiceByReference('IN-5')?->is($in) ?? false
        );
    }

    public function test_a_fully_paid_invoice_is_not_matched_again(): void
    {
        $invoice = $this->invoice(10000);

        $this->sendReceipt(['TransID' => 'FIRST01'])->assertOk();
        $this->sendReceipt(['TransID' => 'SECOND02'])->assertOk();

        $invoice->refresh();

        $this->assertEquals(10000, $invoice->applied_amount);
        $this->assertSame(
            MpesaTransaction::ALLOCATION_UNMATCHED,
            MpesaTransaction::where('trans_id', 'SECOND02')->first()->allocation_status,
        );
    }

    public function test_a_draft_cannot_be_paid(): void
    {
        $draft = $this->invoice(10000);
        $draft->update(['doc_type' => Invoice::TYPE_DRAFT, 'status' => Invoice::STATUS_DRAFT]);

        $this->sendReceipt()->assertOk();

        $this->assertEquals(0, $draft->fresh()->applied_amount);
    }

    public function test_a_cancelled_invoice_cannot_be_paid(): void
    {
        $invoice = $this->invoice(10000, status: Invoice::STATUS_CANCELLED);

        $this->sendReceipt()->assertOk();

        $this->assertEquals(0, $invoice->fresh()->applied_amount);
    }

    /**
     * A payment must never be applied to an invoice awaiting approval as if
     * that approval had happened.
     */
    public function test_paying_a_pending_invoice_does_not_bypass_approval(): void
    {
        $invoice = $this->invoice(10000, status: Invoice::STATUS_PENDING_APPROVAL);

        $this->sendReceipt()->assertOk();

        $invoice->refresh();

        $this->assertEquals(10000, $invoice->applied_amount);
        $this->assertSame(Invoice::STATUS_PENDING_APPROVAL, $invoice->status);
    }

    public function test_a_manual_allocation_records_who_did_it(): void
    {
        $invoice = $this->invoice(10000);
        $this->sendReceipt(['BillRefNumber' => 'WRONG'])->assertOk();

        $transaction = MpesaTransaction::first();
        $user = User::factory()->create();

        app(PaymentAllocationService::class)->allocate(
            $transaction, $invoice, 10000, PaymentAllocation::MATCHED_MANUAL, $user->id,
        );

        $allocation = PaymentAllocation::firstOrFail();

        $this->assertSame(PaymentAllocation::MATCHED_MANUAL, $allocation->matched_by);
        $this->assertEquals($user->id, $allocation->allocated_by);
        $this->assertEquals(0, $invoice->fresh()->balance_due);
    }

    public function test_allocating_more_than_the_receipt_holds_is_rejected(): void
    {
        $invoice = $this->invoice(50000);
        $this->sendReceipt(['TransAmount' => '1000.00', 'BillRefNumber' => 'WRONG'])->assertOk();

        $this->expectException(ValidationException::class);

        app(PaymentAllocationService::class)->allocate(MpesaTransaction::first(), $invoice, 5000);
    }

    public function test_unallocating_restores_both_balances(): void
    {
        $invoice = $this->invoice(10000);
        $this->sendReceipt()->assertOk();

        $allocation = PaymentAllocation::firstOrFail();
        app(PaymentAllocationService::class)->unallocate($allocation);

        $invoice->refresh();

        $this->assertEquals(0, $invoice->applied_amount);
        $this->assertEquals(10000, $invoice->balance_due);
        $this->assertSame(Invoice::STATUS_OPEN, $invoice->status);
        $this->assertSame(MpesaTransaction::ALLOCATION_UNMATCHED, MpesaTransaction::first()->allocation_status);
    }

    /**
     * Safaricom retries until it gets a success; a retry must not pay twice.
     */
    public function test_a_retried_callback_does_not_double_pay(): void
    {
        $invoice = $this->invoice(10000);

        $this->sendReceipt(['TransID' => 'RETRY01', 'TransAmount' => '4000.00'])->assertOk();
        $this->sendReceipt(['TransID' => 'RETRY01', 'TransAmount' => '4000.00'])->assertOk();

        $this->assertSame(1, MpesaTransaction::count());
        $this->assertSame(1, PaymentAllocation::count());
        $this->assertEquals(4000, $invoice->fresh()->applied_amount);
    }

    /**
     * A validation callback is a question, not money received.
     */
    public function test_a_validation_callback_does_not_allocate(): void
    {
        $invoice = $this->invoice(10000);

        $this->postJson('/api/mpesa/c2b/validation', [
            'TransID' => 'VAL001', 'TransAmount' => '10000.00', 'BillRefNumber' => 'IN-1',
        ])->assertOk();

        $this->assertEquals(0, $invoice->fresh()->applied_amount);
    }
}
