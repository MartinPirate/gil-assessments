<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\VatCodes\Pages\EditVatCode;
use App\Filament\Resources\VatCodes\Pages\ListVatCodes;
use App\Filament\Resources\VatCodes\VatCodeResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\User;
use App\Models\VatCode;
use App\Models\Warehouse;
use App\Services\InvoiceWriter;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * VAT codes: a rate is legislation, so it is data somebody can edit — but a
 * document already posted must keep what it was charged.
 */
class VatCodeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    public function test_only_administrators_reach_the_screen(): void
    {
        foreach ([UserRole::Sales, UserRole::Manager, UserRole::GateOfficer, UserRole::Driver] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->assertFalse(VatCodeResource::canAccess(), "{$role->value} must not edit tax rates.");
        }

        $this->actingAs($this->admin);
        $this->assertTrue(VatCodeResource::canAccess());
    }

    public function test_the_screen_lists_the_seeded_codes(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListVatCodes::class)
            ->assertCanSeeTableRecords(VatCode::all());
    }

    /**
     * Two defaults would make VatCode::default() depend on row order, and new
     * lines would start at a different rate depending on the day.
     */
    public function test_making_a_code_the_default_takes_it_from_the_previous_one(): void
    {
        $previous = VatCode::where('is_default', true)->firstOrFail();
        $other = VatCode::where('is_default', false)->firstOrFail();

        $other->update(['is_default' => true]);

        $this->assertFalse($previous->fresh()->is_default);
        $this->assertTrue($other->fresh()->is_default);
        $this->assertSame(1, VatCode::where('is_default', true)->count());
        $this->assertTrue($other->is($this->freshDefault()));
    }

    public function test_the_screen_cannot_leave_two_defaults_behind(): void
    {
        $other = VatCode::where('is_default', false)->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(EditVatCode::class, ['record' => $other->getKey()])
            ->fillForm(['is_default' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, VatCode::where('is_default', true)->count());
    }

    /**
     * The whole reason this is a table and not an enum — and the reason the
     * line keeps its own vat_rate.
     */
    public function test_changing_a_rate_does_not_restate_documents_already_posted(): void
    {
        $standard = VatCode::where('code', 'V16')->firstOrFail();

        $invoice = $this->anInvoiceTaxedAt($standard);
        $line = $invoice->lines->first();

        $this->assertEquals(16.0, (float) $line->vat_rate);
        $taxAtPosting = (float) $invoice->tax_total;

        // Kenya did exactly this in April 2020.
        $standard->update(['rate' => 14]);

        $invoice->refresh();

        $this->assertEquals(16.0, (float) $invoice->lines->first()->vat_rate);
        $this->assertEqualsWithDelta($taxAtPosting, (float) $invoice->tax_total, 0.001);
    }

    public function test_a_new_document_picks_up_the_changed_rate(): void
    {
        $standard = VatCode::where('code', 'V16')->firstOrFail();
        $standard->update(['rate' => 14]);

        $invoice = $this->anInvoiceTaxedAt($standard->fresh());

        $this->assertEquals(14.0, (float) $invoice->lines->first()->vat_rate);
    }

    protected function freshDefault(): ?VatCode
    {
        return VatCode::default();
    }

    protected function anInvoiceTaxedAt(VatCode $vatCode): Invoice
    {
        $this->actingAs($this->admin);

        $customer = Customer::firstOrCreate(
            ['code' => 'CC1'],
            ['name' => 'Naivas Supermarket Ltd', 'currency' => 'KES'],
        );

        $item = Item::firstOrCreate(
            ['item_no' => 'FG00011'],
            [
                'description' => 'Umi All Purpose Home Baking Flour 2Kg',
                'uom' => 'Bales',
                'warehouse_id' => Warehouse::where('code', 'FG WHS')->value('id'),
                'unit_price' => 1000,
                'qty_in_warehouse' => 100,
            ],
        );

        return app(InvoiceWriter::class)->store([
            'customer_id' => $customer->getKey(),
            'posting_date' => now()->toDateString(),
            'series' => 'IN',
            'remarks' => 'VAT rate test',
            'lines' => [[
                'item_id' => $item->getKey(),
                'item_description' => $item->description,
                'warehouse_id' => $item->warehouse_id,
                'vat_code_id' => $vatCode->getKey(),
                'quantity' => 1,
                'price_before_discount' => 1000,
                'discount_percent' => 0,
            ]],
        ], $this->admin->getKey())->load('lines');
    }
}
