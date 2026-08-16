<?php

namespace Tests\Feature;

use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceFreightCharge;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Models\User;
use App\Models\VatCode;
use App\Models\Warehouse;
use App\Services\InvoiceWriter;
use App\Support\InvoiceCalculator;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Freight, itemised — and taxed.
 */
class FreightChargesTest extends TestCase
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
        $this->actingAs($this->user);

        $this->customer = Customer::create(['code' => 'CC1', 'name' => 'Naivas', 'currency' => 'KES']);

        $this->item = Item::create([
            'item_no' => 'FG00011',
            'description' => 'Umi All Purpose Home Baking Flour 2Kg',
            'uom' => 'Bales',
            'warehouse_id' => Warehouse::where('code', 'FG WHS')->value('id'),
            'unit_price' => 1000,
            'qty_in_warehouse' => 100,
        ]);
    }

    protected function standardRate(): VatCode
    {
        return VatCode::where('code', 'V16')->firstOrFail();
    }

    protected function zeroRate(): VatCode
    {
        return VatCode::where('code', 'O0')->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $charges
     */
    protected function invoiceWith(array $charges, float $discountPercent = 0): Invoice
    {
        $employee = SalesEmployee::firstOrCreate(['code' => 'SE001'], ['name' => 'Farouk Mohamed']);

        return app(InvoiceWriter::class)->store([
            'customer_id' => $this->customer->getKey(),
            'sales_employee_id' => $employee->getKey(),
            'posting_date' => now()->toDateString(),
            'series' => 'IN',
            'remarks' => 'Freight test',
            'discount_percent' => $discountPercent,
            'freight_charges' => $charges,
            'lines' => [[
                'item_id' => $this->item->getKey(),
                'quantity' => 1,
                'price_before_discount' => 1000,
                'vat_code_id' => $this->zeroRate()->getKey(),
            ]],
        ], $this->user->getKey());
    }

    public function test_charges_are_stored_against_the_document(): void
    {
        $invoice = $this->invoiceWith([
            ['description' => 'Delivery', 'amount' => 3500, 'vat_code_id' => $this->standardRate()->getKey(), 'remarks' => 'Nairobi to Mombasa'],
            ['description' => 'Insurance', 'amount' => 1200, 'vat_code_id' => $this->zeroRate()->getKey()],
        ]);

        $charges = $invoice->freightCharges;

        $this->assertCount(2, $charges);
        $this->assertSame('Delivery', $charges->first()->description);
        $this->assertSame('Nairobi to Mombasa', $charges->first()->remarks);
        $this->assertEquals([1, 2], $charges->pluck('line_num')->all());
    }

    /**
     * The point of the whole exercise: freight used to contribute nothing to
     * VAT, because tax came from the lines and freight was added afterwards.
     */
    public function test_freight_is_taxed_at_its_own_rate(): void
    {
        $invoice = $this->invoiceWith([
            ['description' => 'Delivery', 'amount' => 1000, 'vat_code_id' => $this->standardRate()->getKey()],
        ]);

        // Goods are zero rated here, so every shilling of tax is the freight's.
        $this->assertEqualsWithDelta(160.0, (float) $invoice->tax_total, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $invoice->freight, 0.01);
        $this->assertEqualsWithDelta(2160.0, (float) $invoice->document_total, 0.01);
    }

    /**
     * Charges genuinely differ — delivery is standard rated, insurance
     * usually is not — so a blanket rate on the total would be wrong.
     */
    public function test_each_charge_carries_its_own_rate(): void
    {
        $invoice = $this->invoiceWith([
            ['description' => 'Delivery', 'amount' => 1000, 'vat_code_id' => $this->standardRate()->getKey()],
            ['description' => 'Insurance', 'amount' => 1000, 'vat_code_id' => $this->zeroRate()->getKey()],
        ]);

        $this->assertEqualsWithDelta(160.0, (float) $invoice->tax_total, 0.01);

        $rates = $invoice->freightCharges->pluck('vat_rate')->map(fn ($r) => (float) $r);
        $this->assertEqualsCanonicalizing([16.0, 0.0], $rates->all());
    }

    /**
     * The rate is read from the chosen code, never from the payload — the same
     * rule the invoice lines follow.
     */
    public function test_a_posted_rate_cannot_lower_the_tax(): void
    {
        $invoice = $this->invoiceWith([
            ['description' => 'Delivery', 'amount' => 1000, 'vat_code_id' => $this->standardRate()->getKey(), 'vat_rate' => 0],
        ]);

        $this->assertEqualsWithDelta(16.0, (float) $invoice->freightCharges->first()->vat_rate, 0.001);
        $this->assertEqualsWithDelta(160.0, (float) $invoice->tax_total, 0.01);
    }

    /**
     * A document discount is on the goods. Delivery is delivery.
     */
    public function test_a_document_discount_does_not_reduce_freight(): void
    {
        $invoice = $this->invoiceWith(
            [['description' => 'Delivery', 'amount' => 1000, 'vat_code_id' => $this->standardRate()->getKey()]],
            discountPercent: 50,
        );

        $this->assertEqualsWithDelta(1000.0, (float) $invoice->freight, 0.01);
        $this->assertEqualsWithDelta(160.0, (float) $invoice->tax_total, 0.01);
        // 1000 goods less 50%, plus untouched freight and its tax.
        $this->assertEqualsWithDelta(1660.0, (float) $invoice->document_total, 0.01);
    }

    public function test_blank_rows_are_not_stored(): void
    {
        $invoice = $this->invoiceWith([
            ['description' => 'Delivery', 'amount' => 500, 'vat_code_id' => $this->zeroRate()->getKey()],
            ['description' => null, 'amount' => 0],
        ]);

        $this->assertCount(1, $invoice->freightCharges);
    }

    /**
     * Documents written before this existed pass a bare figure, and must
     * still behave.
     */
    public function test_a_plain_freight_figure_still_works(): void
    {
        $totals = InvoiceCalculator::documentTotals([], 0, 750.0);

        $this->assertEqualsWithDelta(750.0, $totals['freight'], 0.01);
        $this->assertEqualsWithDelta(0.0, $totals['tax_total'], 0.01);
    }

    public function test_deleting_the_invoice_takes_its_charges(): void
    {
        $invoice = $this->invoiceWith([
            ['description' => 'Delivery', 'amount' => 500, 'vat_code_id' => $this->zeroRate()->getKey()],
        ]);

        $id = $invoice->getKey();
        $invoice->delete();

        $this->assertSame(0, InvoiceFreightCharge::where('invoice_id', $id)->count());
    }

    /**
     * Saved charges have to be visible afterwards, not just folded into one
     * figure nobody can take apart.
     */
    public function test_the_charges_are_listed_on_the_document(): void
    {
        $invoice = $this->invoiceWith([
            ['description' => 'Delivery', 'amount' => 3500, 'vat_code_id' => $this->standardRate()->getKey(), 'remarks' => 'Nairobi to Mombasa'],
            ['description' => 'Insurance', 'amount' => 1200, 'vat_code_id' => $this->zeroRate()->getKey()],
        ]);

        Livewire::actingAs($this->user)
            ->test(ViewInvoice::class, ['record' => $invoice->getKey()])
            ->assertSuccessful()
            ->assertSee('Freight charges')
            ->assertSee('Delivery')
            ->assertSee('Insurance')
            ->assertSee('Nairobi to Mombasa')
            ->assertSee('3,500.00');
    }

    /**
     * A section headed "Freight charges" over an empty table on every document
     * that had none would be furniture.
     */
    public function test_a_document_without_freight_shows_no_such_section(): void
    {
        $invoice = $this->invoiceWith([]);

        Livewire::actingAs($this->user)
            ->test(ViewInvoice::class, ['record' => $invoice->getKey()])
            ->assertSuccessful()
            ->assertDontSee('Freight charges');
    }
}
