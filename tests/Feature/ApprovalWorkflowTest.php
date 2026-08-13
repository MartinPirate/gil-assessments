<?php

namespace Tests\Feature;

use App\Filament\Pages\ArInvoice;
use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Drafts and the approval queue behind the threshold label.
 */
class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $sales;

    protected Customer $customer;

    protected Item $item;

    protected SalesEmployee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);

        $this->sales = User::factory()->sales()->create();

        $this->customer = Customer::create([
            'code' => 'CC00001', 'name' => 'Walk In Customer - HQ', 'currency' => 'KES',
        ]);

        $this->item = Item::create([
            'item_no' => 'FG00011', 'description' => 'Flour 2Kg', 'uom' => 'Bales',
            'warehouse' => 'FG WHS', 'unit_price' => 1850, 'qty_in_warehouse' => 648,
        ]);

        $this->employee = SalesEmployee::create(['code' => 'SE001', 'name' => 'Farouk Mohamed']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function document(array $overrides = [], float $quantity = 20): array
    {
        return array_merge([
            'series' => 'IN',
            'posting_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'sales_employee_id' => $this->employee->id,
            'remarks' => 'Test order',
            'discount_percent' => 0,
            'lines' => [[
                'item_id' => $this->item->id,
                'item_no' => 'FG00011',
                'item_description' => 'Flour 2Kg',
                'uom' => 'Bales',
                'warehouse' => 'FG WHS',
                'quantity' => $quantity,
                'price_before_discount' => 1850,
                'discount_percent' => 0,
            ]],
        ], $overrides);
    }

    public function test_a_document_over_the_threshold_opens_an_approval_request(): void
    {
        Livewire::actingAs($this->sales)
            ->test(ArInvoice::class)
            ->fillForm($this->document())
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice = Invoice::firstOrFail();

        $this->assertSame(Invoice::STATUS_PENDING_APPROVAL, $invoice->status);
        $this->assertSame(1, ApprovalRequest::query()->pending()->count());
        // Both are decimal-cast strings at different scales, so compare numerically.
        $this->assertEqualsWithDelta(
            (float) $invoice->document_total,
            (float) ApprovalRequest::first()->amount,
            0.0001,
        );
    }

    public function test_a_document_under_the_threshold_does_not(): void
    {
        Livewire::actingAs($this->sales)
            ->test(ArInvoice::class)
            ->fillForm($this->document(quantity: 1))    // 1,850
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Invoice::STATUS_OPEN, Invoice::firstOrFail()->status);
        $this->assertSame(0, ApprovalRequest::count());
    }

    /**
     * A draft is a scratchpad, not a receivable, so it must not consume the
     * approver's attention or create a balance.
     */
    public function test_a_draft_is_not_routed_for_approval(): void
    {
        Livewire::actingAs($this->sales)
            ->test(ArInvoice::class)
            ->fillForm($this->document())
            ->call('saveDraft')
            ->assertHasNoFormErrors();

        $invoice = Invoice::firstOrFail();

        $this->assertTrue($invoice->isDraft());
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertEquals(0, $invoice->balance_due);
        $this->assertSame(0, ApprovalRequest::count());
    }

    public function test_approving_opens_the_invoice(): void
    {
        $request = $this->pendingRequest();
        $approver = User::factory()->approver()->create();

        app(ApprovalService::class)->approve($request, $approver, 'Checked against the PO.');

        $this->assertSame(ApprovalRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame(Invoice::STATUS_OPEN, $request->invoice->fresh()->status);
    }

    public function test_rejecting_marks_the_invoice_rejected(): void
    {
        $request = $this->pendingRequest();
        $approver = User::factory()->approver()->create();

        app(ApprovalService::class)->reject($request, $approver, 'Price not agreed.');

        $this->assertSame(ApprovalRequest::STATUS_REJECTED, $request->fresh()->status);
        $this->assertSame(Invoice::STATUS_REJECTED, $request->invoice->fresh()->status);
        $this->assertSame('Price not agreed.', $request->fresh()->decision_reason);
    }

    public function test_a_rejection_must_carry_a_reason(): void
    {
        $this->expectException(ValidationException::class);

        app(ApprovalService::class)->reject(
            $this->pendingRequest(),
            User::factory()->approver()->create(),
            '   ',
        );
    }

    /**
     * Two approvers opening the same queue entry must not both decide it.
     */
    public function test_a_request_cannot_be_decided_twice(): void
    {
        $request = $this->pendingRequest();
        $service = app(ApprovalService::class);

        $service->approve($request, User::factory()->approver()->create());

        $this->expectException(ValidationException::class);

        $service->reject($request->fresh(), User::factory()->approver()->create(), 'Too late');
    }

    public function test_an_approver_cannot_exceed_their_limit(): void
    {
        $request = $this->pendingRequest();          // 37,000
        $junior = User::factory()->approver(10000)->create();

        $this->expectException(ValidationException::class);

        app(ApprovalService::class)->approve($request, $junior);
    }

    public function test_a_salesperson_cannot_approve(): void
    {
        $this->expectException(ValidationException::class);

        app(ApprovalService::class)->approve($this->pendingRequest(), $this->sales);
    }

    protected function pendingRequest(): ApprovalRequest
    {
        Livewire::actingAs($this->sales)
            ->test(ArInvoice::class)
            ->fillForm($this->document())
            ->call('save');

        return ApprovalRequest::query()->pending()->firstOrFail();
    }
}
