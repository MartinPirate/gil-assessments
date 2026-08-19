<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
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
 * The order the register opens in.
 *
 * A register is read as a history — the thing you raised a moment ago belongs
 * at the top. Ordering on doc_num gets that right only while a single series
 * is in play, because the counter is per series: the unique index is on
 * (series, doc_num) so that IN-5 and CR-5 can both exist. These tests hold the
 * order to when documents were written, which stays true however many series
 * are raised.
 */
class InvoiceRegisterOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    public function test_the_register_opens_on_the_most_recently_written_document(): void
    {
        $oldest = $this->travelTo(now()->subDays(2), fn () => $this->writeInvoice());
        $middle = $this->travelTo(now()->subDay(), fn () => $this->writeInvoice());
        $newest = $this->writeInvoice();

        Livewire::actingAs($this->admin)
            ->test(ListInvoices::class)
            ->assertCanSeeTableRecords([$newest, $middle, $oldest], inOrder: true);
    }

    /**
     * The case doc_num cannot answer. CR-1 is written last and must lead, even
     * though IN-2 carries the higher number.
     */
    public function test_a_second_series_does_not_disturb_the_order(): void
    {
        $firstInvoice = $this->travelTo(now()->subDays(2), fn () => $this->writeInvoice('IN'));
        $secondInvoice = $this->travelTo(now()->subDay(), fn () => $this->writeInvoice('IN'));
        $creditNote = $this->writeInvoice('CR');

        // The premise, asserted rather than assumed: a fresh series starts its
        // own count, so the document written last carries the lower number.
        // Stated as a relationship, because what the IN counter has reached by
        // now depends on which tests ran before this one.
        $this->assertLessThan(
            $secondInvoice->doc_num,
            $creditNote->doc_num,
            'The case only bites when the newest document has the lower number.',
        );

        Livewire::actingAs($this->admin)
            ->test(ListInvoices::class)
            ->assertCanSeeTableRecords([$creditNote, $secondInvoice, $firstInvoice], inOrder: true);
    }

    /**
     * Documents written inside the same second share a created_at. Without the
     * id behind it the order is not total, and a row can sit on two pages or
     * neither as the query is repeated.
     */
    public function test_documents_written_together_still_have_one_order(): void
    {
        $now = now();

        $first = $this->travelTo($now, fn () => $this->writeInvoice());
        $second = $this->travelTo($now, fn () => $this->writeInvoice());
        $third = $this->travelTo($now, fn () => $this->writeInvoice());

        $this->assertSame(
            $first->created_at->toDateTimeString(),
            $third->created_at->toDateTimeString(),
            'The premise of this test is that the timestamps collide.',
        );

        Livewire::actingAs($this->admin)
            ->test(ListInvoices::class)
            ->assertCanSeeTableRecords([$third, $second, $first], inOrder: true);
    }

    protected function writeInvoice(string $series = 'IN'): Invoice
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
            'series' => $series,
            'remarks' => 'Register order test',
            'lines' => [[
                'item_id' => $item->getKey(),
                'item_description' => $item->description,
                'warehouse_id' => $item->warehouse_id,
                'vat_code_id' => VatCode::query()->value('id'),
                'quantity' => 1,
                'price_before_discount' => 1000,
                'discount_percent' => 0,
            ]],
        ], $this->admin->getKey());
    }
}
