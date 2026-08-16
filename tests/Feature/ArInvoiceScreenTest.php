<?php

namespace Tests\Feature;

use App\Filament\Pages\ArInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 1 — the A/R Invoice screen end to end.
 */
class ArInvoiceScreenTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected Item $item;

    protected SalesEmployee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->user = User::factory()->create();

        $this->customer = Customer::create([
            'code' => 'CC00001',
            'name' => 'Walk In Customer - HQ',
            'currency' => 'KES',
            'kra_pin' => 'P051234567X',
        ]);

        // The first contact created becomes the customer's default, which is
        // the name the document picks up.
        $this->customer->contactPeople()->create([
            'name' => 'Jane Wanjiru',
            'email' => 'jane.wanjiru@naivas.co.ke',
            'phone' => '+254711234001',
        ]);

        $this->customer->refresh();

        $this->item = Item::create([
            'item_no' => 'FG00011',
            'description' => 'Umi All Purpose Home Baking Flour 2Kg',
            'uom' => 'Bales',
            'warehouse_id' => Warehouse::where('code', 'FG WHS')->value('id'),
            'unit_price' => 1850,
            'qty_in_warehouse' => 648,
        ]);

        $this->employee = SalesEmployee::create([
            'code' => 'SE001',
            'name' => 'Farouk Abdulrehman Mohamed',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validDocument(array $overrides = []): array
    {
        return array_merge([
            'series' => 'IN',
            'posting_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'sales_employee_id' => $this->employee->id,
            'remarks' => 'Test order',
            'discount_percent' => 0,
            'lines' => [
                [
                    'item_id' => $this->item->id,
                    'item_description' => $this->item->description,
                    'warehouse_id' => Warehouse::where('code', 'FG WHS')->value('id'),
                    'quantity' => 2,
                    'price_before_discount' => 1850,
                    'discount_percent' => 0,
                    'price_after_discount' => 1850,
                ],
            ],
        ], $overrides);
    }

    public function test_selecting_a_customer_populates_the_header(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->set('data.customer_id', $this->customer->id)
            ->assertSet('data.contact_person', 'Jane Wanjiru')
            ->assertSet('data.currency', 'KES')
            ->assertSet('data.kra_pin', 'P051234567X');
    }

    public function test_posting_date_defaults_to_today(): void
    {
        $component = Livewire::actingAs($this->user)->test(ArInvoice::class);

        // A non-native date picker keeps "Y-m-d H:i:s" in the Livewire state
        // and only narrows to "Y-m-d" on getState(), so compare the date part.
        $this->assertStringStartsWith(
            now()->toDateString(),
            (string) $component->instance()->data['posting_date'],
        );
    }

    public function test_an_invoice_is_saved_with_its_lines_and_totals(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($this->validDocument())
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice = Invoice::with('lines')->firstOrFail();

        $this->assertSame('CC00001', $invoice->customer_code);
        $this->assertSame('Walk In Customer - HQ', $invoice->customer_name);
        $this->assertSame('Farouk Abdulrehman Mohamed', $invoice->sales_employee_name);
        $this->assertEquals(3700.000, $invoice->total_before_discount);
        $this->assertEquals(3700.000, $invoice->total_after_discount);
        // pdo_sqlsrv returns bigints as strings, so compare loosely.
        $this->assertEquals($this->user->id, $invoice->created_by);

        $this->assertCount(1, $invoice->lines);
        $this->assertEquals(1850.000, $invoice->lines->first()->price_after_discount);
        $this->assertEquals(3700.000, $invoice->lines->first()->line_total);
    }

    public function test_document_numbers_increment_sequentially(): void
    {
        foreach ([1, 2, 3] as $expected) {
            Livewire::actingAs($this->user)
                ->test(ArInvoice::class)
                ->fillForm($this->validDocument())
                ->call('save')
                ->assertHasNoFormErrors();

            $this->assertSame($expected, (int) Invoice::latest('id')->first()->doc_num);
        }
    }

    public function test_totals_over_the_threshold_are_flagged_for_approval(): void
    {
        $document = $this->validDocument();
        $document['lines'][0]['quantity'] = 20;   // 20 x 1850 = 37,000

        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($document)
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice = Invoice::firstOrFail();

        $this->assertTrue($invoice->requires_approval);
        $this->assertSame('Pending Approval', $invoice->status);
        $this->assertEquals(37000.000, $invoice->total_after_discount);
    }

    public function test_totals_under_the_threshold_are_not_flagged(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($this->validDocument())    // 3,700
            ->call('save');

        $invoice = Invoice::firstOrFail();

        $this->assertFalse($invoice->requires_approval);
        $this->assertSame('Open', $invoice->status);
    }

    public function test_the_approval_label_appears_only_above_the_threshold(): void
    {
        $component = Livewire::actingAs($this->user)->test(ArInvoice::class);

        $component->set('data.lines.0.quantity', 1)
            ->set('data.lines.0.price_before_discount', 100)
            ->call('recalculateAllTotals');
        $this->assertFalse($component->instance()->requiresApproval());

        $component->set('data.lines.0.price_before_discount', 20000)
            ->call('recalculateAllTotals');
        $this->assertTrue($component->instance()->requiresApproval());
        $this->assertSame(
            'Invoice will go for approval – Amount: 20,000.00',
            $component->instance()->getApprovalMessage(),
        );
    }

    public function test_remarks_are_mandatory(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($this->validDocument(['remarks' => '']))
            ->call('save')
            ->assertHasFormErrors(['remarks' => 'required']);

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_line_discount_over_fifty_percent_is_rejected(): void
    {
        $document = $this->validDocument();
        $document['lines'][0]['discount_percent'] = 60;

        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($document)
            ->call('save')
            ->assertHasFormErrors(['lines.0.discount_percent']);

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_document_discount_over_fifty_percent_is_rejected(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($this->validDocument(['discount_percent' => 51]))
            ->call('save')
            ->assertHasFormErrors(['discount_percent']);

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_discount_of_exactly_fifty_percent_is_allowed(): void
    {
        $document = $this->validDocument();
        $document['lines'][0]['discount_percent'] = 50;

        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($document)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(1850.000, Invoice::firstOrFail()->total_after_discount);
    }

    /**
     * Totals are recomputed server-side, so tampering with the posted display
     * values must not change what is stored.
     */
    public function test_posted_totals_are_ignored_in_favour_of_recalculation(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($this->validDocument())
            ->set('data.total_before_discount', 999999)
            ->set('data.total_after_discount', 999999)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(3700.000, Invoice::firstOrFail()->total_after_discount);
    }

    public function test_a_document_with_no_usable_lines_is_rejected(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($this->validDocument(['lines' => [[
                'item_id' => null,
                'item_no' => null,
                'item_description' => null,
                'quantity' => null,
                'price_before_discount' => null,
                'discount_percent' => 0,
            ]]]))
            ->call('save')
            ->assertHasErrors();

        $this->assertSame(0, Invoice::count());
    }

    public function test_choose_from_list_selection_populates_the_customer(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->dispatch(
                'choose-from-list-selected',
                statePath: 'data.customer_id',
                recordId: $this->customer->id,
                source: 'customers_by_code',
            )
            ->assertSet('data.customer_id', (string) $this->customer->id)
            ->assertSet('data.contact_person', 'Jane Wanjiru');
    }

    /**
     * data_set() treats "*" as a wildcard, so an unfiltered path would write
     * the chosen id into every sibling key of the repeater.
     */
    public function test_choose_from_list_rejects_wildcard_state_paths(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->set('data.lines.0.quantity', 5)
            ->dispatch(
                'choose-from-list-selected',
                statePath: 'data.lines.*.item_id',
                recordId: $this->item->id,
                source: 'items',
            );

        $this->assertNull($component->instance()->data['lines'][0]['item_id'] ?? null);
    }

    /**
     * The picker sends a state path from the browser; anything outside the
     * form's own state must be ignored rather than written to the component.
     */
    public function test_choose_from_list_ignores_paths_outside_the_form(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->dispatch(
                'choose-from-list-selected',
                statePath: 'nextDocNum',
                recordId: 999,
                source: 'customers_by_code',
            );

        $this->assertNotSame(999, $component->instance()->nextDocNum);
    }

    /**
     * The ETR barcode is the one TIMS field a person fills in — it is scanned
     * off the paper receipt — so it has to survive the save.
     */
    public function test_the_etr_barcode_is_saved_and_stamped(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($this->validDocument(['etr_barcode' => '0060012345678901']))
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice = Invoice::firstOrFail();

        $this->assertSame('0060012345678901', $invoice->etr_barcode);
        $this->assertNotNull($invoice->etr_scanned_at, 'A scanned barcode should be stamped with when it arrived.');
    }

    /**
     * An unscanned document must not claim a scan time.
     */
    public function test_an_invoice_without_a_barcode_is_not_stamped(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($this->validDocument())
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice = Invoice::firstOrFail();

        $this->assertNull($invoice->etr_barcode);
        $this->assertNull($invoice->etr_scanned_at);
    }

    /**
     * "Up to 3 decimal places" has to be enforced, not merely displayed.
     *
     * The number input's step is a hint the browser honours and a paste
     * ignores, so the refusal lives on the server as well. Caught here on the
     * down payment, which is where a fourth decimal was getting through.
     */
    public function test_a_fourth_decimal_place_is_refused(): void
    {
        $document = $this->validDocument();
        $document['total_down_payment'] = 0.0015;

        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($document)
            ->call('save')
            ->assertHasFormErrors(['total_down_payment']);

        $this->assertSame(0, Invoice::count());
    }

    public function test_three_decimal_places_are_accepted(): void
    {
        $document = $this->validDocument();
        $document['total_down_payment'] = 0.001;

        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($document)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Invoice::count());
    }

    /**
     * The same bound on the columns the brief names.
     */
    public function test_a_fourth_decimal_place_is_refused_on_the_line_columns(): void
    {
        $document = $this->validDocument();
        $document['lines'][0]['price_before_discount'] = 1850.0625;

        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($document)
            ->call('save')
            ->assertHasFormErrors(['lines.0.price_before_discount']);

        $document = $this->validDocument();
        $document['lines'][0]['discount_percent'] = 5.405405;

        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->fillForm($document)
            ->call('save')
            ->assertHasFormErrors(['lines.0.discount_percent']);
    }
}
