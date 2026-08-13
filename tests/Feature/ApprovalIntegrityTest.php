<?php

namespace Tests\Feature;

use App\Filament\Resources\Approvals\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The invariant: an invoice marked "Pending Approval" always has an open
 * request to decide.
 *
 * When it is violated the document shows as pending forever with nothing in
 * the queue — and because the approvals list is filtered to Pending by
 * default, nobody notices.
 */
class ApprovalIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function stuckInvoice(float $total = 15336, bool $withLines = true): Invoice
    {
        $customer = Customer::create(['code' => 'CC1', 'name' => 'Quickmart', 'currency' => 'KES']);

        $invoice = Invoice::create([
            'doc_num' => 1, 'series' => 'IN', 'doc_type' => Invoice::TYPE_INVOICE,
            'customer_id' => $customer->id, 'customer_code' => 'CC1', 'customer_name' => 'Quickmart',
            'currency' => 'KES', 'posting_date' => now()->toDateString(), 'remarks' => 'legacy',
            // Deliberately zero, as a document written before the column existed.
            'document_total' => 0,
            'requires_approval' => true,
            'status' => Invoice::STATUS_PENDING_APPROVAL,
        ]);

        if ($withLines) {
            InvoiceLine::create([
                'invoice_id' => $invoice->id, 'line_num' => 1,
                'item_no' => 'FG00015', 'item_description' => 'Maize Meal 2Kg',
                'quantity' => 12, 'price_before_discount' => 1420, 'discount_percent' => 10,
                'price_after_discount' => 1278, 'line_total' => $total, 'vat_rate' => 0,
            ]);
        }

        return $invoice;
    }

    public function test_a_stuck_invoice_is_detected(): void
    {
        $this->stuckInvoice();

        $this->assertSame(1, ApprovalRequestResource::stuckInvoiceCount());
    }

    public function test_a_healthy_pending_invoice_is_not_flagged(): void
    {
        $invoice = $this->stuckInvoice();

        ApprovalRequest::create([
            'invoice_id' => $invoice->id, 'amount' => 15336, 'threshold' => Invoice::APPROVAL_THRESHOLD,
            'status' => ApprovalRequest::STATUS_PENDING,
            'requested_by' => User::factory()->create()->id, 'requested_at' => now(),
        ]);

        $this->assertSame(0, ApprovalRequestResource::stuckInvoiceCount());
    }

    /**
     * A decided request does not un-stick the invoice — it still has nothing
     * open to act on.
     */
    public function test_a_decided_request_does_not_count_as_open(): void
    {
        $invoice = $this->stuckInvoice();

        ApprovalRequest::create([
            'invoice_id' => $invoice->id, 'amount' => 15336, 'threshold' => Invoice::APPROVAL_THRESHOLD,
            'status' => ApprovalRequest::STATUS_APPROVED,
            'requested_by' => User::factory()->create()->id, 'requested_at' => now(),
        ]);

        $this->assertSame(1, ApprovalRequestResource::stuckInvoiceCount());
    }

    public function test_the_repair_command_opens_a_request(): void
    {
        $invoice = $this->stuckInvoice();

        $this->artisan('invoices:repair-approvals')->assertExitCode(0);

        $request = ApprovalRequest::where('invoice_id', $invoice->id)->firstOrFail();

        $this->assertSame(ApprovalRequest::STATUS_PENDING, $request->status);
        $this->assertSame(0, ApprovalRequestResource::stuckInvoiceCount());
    }

    /**
     * A zero total predates the column; the request must carry a real figure,
     * recomputed from the invoice's own lines.
     */
    public function test_the_repair_recomputes_a_missing_total(): void
    {
        $invoice = $this->stuckInvoice();

        $this->assertEquals(0, $invoice->document_total);

        $this->artisan('invoices:repair-approvals');

        $invoice->refresh();
        $request = ApprovalRequest::where('invoice_id', $invoice->id)->firstOrFail();

        $this->assertEqualsWithDelta(15336, (float) $invoice->document_total, 0.01);
        $this->assertEqualsWithDelta(15336, (float) $request->amount, 0.01);
    }

    public function test_the_dry_run_writes_nothing(): void
    {
        $this->stuckInvoice();

        $this->artisan('invoices:repair-approvals --dry')->assertExitCode(0);

        $this->assertSame(0, ApprovalRequest::count());
        $this->assertSame(1, ApprovalRequestResource::stuckInvoiceCount());
    }

    public function test_the_command_is_safe_to_run_when_nothing_is_stuck(): void
    {
        $this->artisan('invoices:repair-approvals')
            ->expectsOutputToContain('No stuck invoices')
            ->assertExitCode(0);
    }

    /**
     * Running it twice must not open a second request for the same document.
     */
    public function test_the_repair_is_idempotent(): void
    {
        $this->stuckInvoice();

        $this->artisan('invoices:repair-approvals');
        $this->artisan('invoices:repair-approvals');

        $this->assertSame(1, ApprovalRequest::count());
    }
}
