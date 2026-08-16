<?php

namespace Tests\Feature;

use App\Filament\Pages\ArInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InvoiceWriter;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The document header and the line grid, as the client behaves.
 */
class ArInvoiceGridTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected Item $item;

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
        $this->customer->contactPeople()->create(['name' => 'TEST TEST']);
        $this->customer->refresh();

        $this->item = Item::create([
            'item_no' => 'FG00015',
            'description' => 'Umi Maize Meal 2Kg',
            'uom' => 'Bales',
            'warehouse_id' => Warehouse::where('code', 'FG WHS')->value('id'),
            'unit_price' => 1420,
            'qty_in_warehouse' => 890,
        ]);
    }

    /**
     * Distinct from the business partner's name: this is what gets printed on
     * the document. It starts from the master record rather than blank.
     */
    public function test_choosing_a_customer_fills_the_document_customer_name(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->set('data.customer_id', $this->customer->id)
            ->assertSet('data.customer_display_name', 'Walk In Customer - HQ')
            ->assertSet('data.contact_person', 'TEST TEST')
            ->assertSet('data.kra_pin', 'P051234567X')
            ->assertSet('data.currency', 'KES');
    }

    public function test_the_grid_opens_with_one_empty_row(): void
    {
        $component = Livewire::actingAs($this->user)->test(ArInvoice::class);

        $this->assertCount(1, $component->instance()->data['lines']);
    }

    /**
     * The client has no "add row" button — filling the last row produces
     * another beneath it.
     */
    public function test_filling_the_last_row_appends_another(): void
    {
        $component = Livewire::actingAs($this->user)->test(ArInvoice::class);

        $key = array_key_first($component->instance()->data['lines']);

        $component->set("data.lines.{$key}.item_id", $this->item->id)
            ->set("data.lines.{$key}.quantity", 6)
            ->call('recalculateAllTotals');

        $lines = $component->instance()->data['lines'];

        $this->assertCount(2, $lines, 'A second row should be waiting.');

        // The new row is empty, and it is the last one.
        $last = end($lines);
        $this->assertNull($last['item_id']);
        $this->assertNull($last['quantity']);
    }

    public function test_it_does_not_keep_stacking_empty_rows(): void
    {
        $component = Livewire::actingAs($this->user)->test(ArInvoice::class);

        $component->call('recalculateAllTotals')
            ->call('recalculateAllTotals')
            ->call('recalculateAllTotals');

        $this->assertCount(1, $component->instance()->data['lines']);
    }

    /**
     * A second line has to reach the saved document, and the trailing blank
     * must not.
     */
    public function test_two_filled_rows_are_both_saved(): void
    {
        $second = Item::create([
            'item_no' => 'FG00011',
            'description' => 'Umi All Purpose Home Baking Flour 2Kg',
            'uom' => 'Bales',
            'warehouse_id' => $this->item->warehouse_id,
            'unit_price' => 1850,
            'qty_in_warehouse' => 648,
        ]);

        $component = Livewire::actingAs($this->user)->test(ArInvoice::class);

        $first = array_key_first($component->instance()->data['lines']);
        $component->set("data.lines.{$first}.item_id", $this->item->id)
            ->set("data.lines.{$first}.quantity", 6)
            ->set("data.lines.{$first}.price_before_discount", 1420)
            ->call('recalculateAllTotals');

        $keys = array_keys($component->instance()->data['lines']);
        $next = end($keys);

        $component->set("data.lines.{$next}.item_id", $second->id)
            ->set("data.lines.{$next}.quantity", 2)
            ->set("data.lines.{$next}.price_before_discount", 1850)
            ->call('recalculateAllTotals');

        $employee = SalesEmployee::create(['code' => 'SE001', 'name' => 'Farouk Abdulrehman Mohamed']);

        $component->set('data.customer_id', $this->customer->id)
            ->set('data.sales_employee_id', $employee->getKey())
            ->set('data.remarks', 'Two lines')
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice = Invoice::with('lines')->latest('id')->firstOrFail();

        $this->assertCount(2, $invoice->lines);
        $this->assertEqualsWithDelta(6 * 1420 + 2 * 1850, (float) $invoice->total_before_discount, 0.01);
    }

    /**
     * One item, one line. Two lines carrying the same item read as a mistake
     * on the printed document and double the quantity on a stock report.
     */
    public function test_the_same_item_cannot_be_put_on_two_lines(): void
    {
        $component = Livewire::actingAs($this->user)->test(ArInvoice::class);

        $first = array_key_first($component->instance()->data['lines']);
        $component->set("data.lines.{$first}.item_id", $this->item->id);

        $keys = array_keys($component->instance()->data['lines']);
        $second = end($keys);

        $component->set("data.lines.{$second}.item_id", $this->item->id);

        $lines = $component->instance()->data['lines'];

        // The first line keeps it; the second is emptied rather than removed.
        $this->assertEquals($this->item->id, $lines[$first]['item_id']);
        $this->assertNull($lines[$second]['item_id']);
        $this->assertNull($lines[$second]['item_description']);
    }

    /**
     * The screen is not the only way in, so the writer refuses it too.
     */
    public function test_the_writer_refuses_a_payload_repeating_an_item(): void
    {
        $this->expectException(ValidationException::class);

        app(InvoiceWriter::class)->store([
            'customer_id' => $this->customer->getKey(),
            'posting_date' => now()->toDateString(),
            'series' => 'IN',
            'remarks' => 'Repeated item',
            'lines' => [
                ['item_id' => $this->item->getKey(), 'quantity' => 1, 'price_before_discount' => 1420],
                ['item_id' => $this->item->getKey(), 'quantity' => 2, 'price_before_discount' => 1420],
            ],
        ], $this->user->getKey());
    }

    /**
     * An untouched row is blank across every column. Pre-filling the warehouse
     * made a line nobody had entered look half filled in, and put a warehouse
     * on the trailing blank the writer then had to throw away.
     */
    public function test_a_new_row_names_no_warehouse_until_an_item_does(): void
    {
        $component = Livewire::actingAs($this->user)->test(ArInvoice::class);

        $key = array_key_first($component->instance()->data['lines']);

        $this->assertNull($component->instance()->data['lines'][$key]['warehouse_id']);

        // Choosing the item brings its warehouse with it.
        $component->set("data.lines.{$key}.item_id", $this->item->id);

        $this->assertEquals(
            $this->item->warehouse_id,
            $component->instance()->data['lines'][$key]['warehouse_id'],
        );

        // ...and the row that appears beneath is blank again.
        $keys = array_keys($component->instance()->data['lines']);
        $this->assertNull($component->instance()->data['lines'][end($keys)]['warehouse_id']);
    }

    /**
     * A down payment larger than the invoice used to post silently: the
     * calculator clamps the total at zero for display, so 800,000 against a
     * 33,905 document became a total of 0.00 and went in.
     */
    public function test_a_down_payment_cannot_exceed_the_document_total(): void
    {
        $this->expectException(ValidationException::class);

        app(InvoiceWriter::class)->store([
            'customer_id' => $this->customer->getKey(),
            'posting_date' => now()->toDateString(),
            'series' => 'IN',
            'remarks' => 'Overpaid',
            'total_down_payment' => 800000,
            'lines' => [
                ['item_id' => $this->item->getKey(), 'quantity' => 1, 'price_before_discount' => 1420],
            ],
        ], $this->user->getKey());
    }

    /**
     * A down payment up to the document total is fine, and comes off it.
     */
    public function test_a_down_payment_within_the_total_is_accepted(): void
    {
        $employee = SalesEmployee::create(['code' => 'SE002', 'name' => 'Mercy Nyambura']);

        $invoice = app(InvoiceWriter::class)->store([
            'customer_id' => $this->customer->getKey(),
            'sales_employee_id' => $employee->getKey(),
            'posting_date' => now()->toDateString(),
            'series' => 'IN',
            'remarks' => 'Part paid up front',
            'total_down_payment' => 420,
            'lines' => [
                ['item_id' => $this->item->getKey(), 'quantity' => 1, 'price_before_discount' => 1420],
            ],
        ], $this->user->getKey());

        $this->assertEquals(420, (float) $invoice->total_down_payment);
        $this->assertEquals(1000, (float) $invoice->document_total);
    }

    /**
     * Copy From stays live even before a customer is chosen — greyed out it
     * was indistinguishable from Copy To, which is dead for good. It answers
     * when pressed instead.
     */
    public function test_copy_from_is_not_disabled_before_a_customer_is_chosen(): void
    {
        $component = Livewire::actingAs($this->user)->test(ArInvoice::class);

        $this->assertFalse(
            $component->instance()->copyFromAction()->isDisabled(),
            'Copy From must read as live; only Copy To is dead.',
        );

        $this->assertTrue(
            $component->instance()->copyToAction()->isDisabled(),
            'Copy To has nowhere to copy to yet.',
        );
    }
}
