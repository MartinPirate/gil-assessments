<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Widgets\OperationsOverview;
use App\Filament\Widgets\OrderSummary;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard's date filter, and whether the widgets actually read it.
 */
class DashboardFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->admin = User::factory()->role(UserRole::Admin)->create();
        $this->customer = Customer::create(['code' => 'CC1', 'name' => 'Naivas', 'currency' => 'KES']);
    }

    protected function invoiceOn(string $date, float $total): Invoice
    {
        return Invoice::create([
            'doc_num' => random_int(100000, 999999),
            'series' => 'IN',
            'doc_type' => Invoice::TYPE_INVOICE,
            'customer_id' => $this->customer->getKey(),
            'customer_code' => $this->customer->code,
            'customer_name' => $this->customer->name,
            'currency' => 'KES',
            'posting_date' => $date,
            'value_date' => $date,
            'document_date' => $date,
            'remarks' => 'Filter test',
            'status' => Invoice::STATUS_OPEN,
            'document_total' => $total,
            'balance_due' => $total,
            'created_by' => $this->admin->getKey(),
        ]);
    }

    /**
     * The whole point: moving the dates has to change the figures. They used
     * to be ignored entirely, which is worse than having no filter at all.
     */
    public function test_the_order_figures_follow_the_chosen_range(): void
    {
        $this->invoiceOn('2026-03-10', 1000);
        $this->invoiceOn('2026-03-20', 3000);
        $this->invoiceOn('2026-06-15', 9999);

        $march = Livewire::actingAs($this->admin)
            ->test(OrderSummary::class, ['pageFilters' => ['from' => '2026-03-01', 'until' => '2026-03-31']])
            ->html();

        $this->assertStringContainsString('4,000.00', $march, 'March should total 4,000.');
        $this->assertStringNotContainsString('9,999', $march, 'June must not leak into a March range.');

        $june = Livewire::actingAs($this->admin)
            ->test(OrderSummary::class, ['pageFilters' => ['from' => '2026-06-01', 'until' => '2026-06-30']])
            ->html();

        $this->assertStringContainsString('9,999.00', $june);
        $this->assertStringNotContainsString('4,000.00', $june);
    }

    public function test_the_average_is_per_period_not_all_time(): void
    {
        $this->invoiceOn('2026-03-10', 1000);
        $this->invoiceOn('2026-03-20', 3000);
        $this->invoiceOn('2026-06-15', 60000);

        $html = Livewire::actingAs($this->admin)
            ->test(OrderSummary::class, ['pageFilters' => ['from' => '2026-03-01', 'until' => '2026-03-31']])
            ->html();

        // 4,000 over two documents, not 64,000 over three.
        $this->assertStringContainsString('2,000.00', $html);
    }

    public function test_the_invoiced_tile_follows_the_range_rather_than_today(): void
    {
        $this->invoiceOn('2026-03-10', 5500);

        $html = Livewire::actingAs($this->admin)
            ->test(OperationsOverview::class, ['pageFilters' => ['from' => '2026-03-01', 'until' => '2026-03-31']])
            ->html();

        $this->assertStringContainsString('5,500.00', $html);
    }

    /**
     * A percentage needs something to measure against; growth from nothing is
     * not "infinite percent".
     */
    public function test_no_percentage_is_shown_when_there_is_no_previous_period(): void
    {
        $this->invoiceOn('2026-03-10', 1000);

        $html = Livewire::actingAs($this->admin)
            ->test(OrderSummary::class, ['pageFilters' => ['from' => '2026-03-01', 'until' => '2026-03-31']])
            ->html();

        $this->assertStringContainsString('raised', $html);
        $this->assertStringNotContainsString('vs previous period', $html);
    }

    public function test_a_percentage_is_shown_against_the_preceding_window(): void
    {
        // February: 1,000. March: 2,000. Same length either side.
        $this->invoiceOn('2026-02-10', 1000);
        $this->invoiceOn('2026-03-10', 2000);

        $html = Livewire::actingAs($this->admin)
            ->test(OrderSummary::class, ['pageFilters' => ['from' => '2026-03-01', 'until' => '2026-03-28']])
            ->html();

        $this->assertStringContainsString('vs previous period', $html);
        $this->assertStringContainsString('+100%', $html);
    }
}
