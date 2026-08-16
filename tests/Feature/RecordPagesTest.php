<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Drivers\Pages\ViewDriver;
use App\Filament\Resources\SalesEmployees\Pages\ViewSalesEmployee;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\SalesEmployee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The driver and salesperson record pages — history, not four form fields.
 */
class RecordPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    public function test_a_driver_page_shows_their_trips_and_gate_movements(): void
    {
        $driver = Driver::where('national_id', '23456789')->firstOrFail();

        $page = Livewire::actingAs($this->admin)
            ->test(ViewDriver::class, ['record' => $driver->getKey()])
            ->assertSuccessful();

        $page->assertSee($driver->name)
            ->assertSee($driver->national_id)
            ->assertSee('Trips')
            ->assertSee('Gate movements');

        $stats = $page->instance()->stats();

        $this->assertSame($driver->trips()->count(), $stats['trips']);
        $this->assertSame($driver->gateLogs()->count(), $stats['gateMovements']);
    }

    /**
     * The licence state is what the gate actually cares about.
     */
    public function test_a_driver_page_reports_whether_a_licence_is_on_file(): void
    {
        $withLicence = Driver::orderBy('id')->first();
        $without = Driver::orderByDesc('id')->first();

        $this->assertTrue($withLicence->hasLicence());
        $this->assertFalse($without->hasLicence());

        Livewire::actingAs($this->admin)
            ->test(ViewDriver::class, ['record' => $withLicence->getKey()])
            ->assertSee('Licence on file');

        Livewire::actingAs($this->admin)
            ->test(ViewDriver::class, ['record' => $without->getKey()])
            ->assertSee('No licence on file');
    }

    public function test_a_salesperson_page_shows_what_they_have_sold(): void
    {
        $employee = SalesEmployee::query()
            ->whereIn('id', Invoice::query()->select('sales_employee_id'))
            ->firstOrFail();

        $page = Livewire::actingAs($this->admin)
            ->test(ViewSalesEmployee::class, ['record' => $employee->getKey()])
            ->assertSuccessful();

        $page->assertSee($employee->name)
            ->assertSee('Customers')
            ->assertSee('Documents');

        $stats = $page->instance()->stats();

        $this->assertGreaterThan(0, $stats['documents']);
        $this->assertGreaterThan(0, (float) $stats['sold']);
        $this->assertGreaterThan(0, $stats['customers']);
    }

    /**
     * A draft is not a sale. Counting one would flatter the figures.
     */
    public function test_a_salespersons_figures_exclude_drafts(): void
    {
        $employee = SalesEmployee::query()
            ->whereIn('id', Invoice::query()
                ->where('doc_type', Invoice::TYPE_DRAFT)
                ->select('sales_employee_id'))
            ->first();

        if (! $employee) {
            $this->markTestSkipped('No seeded draft carries a sales employee.');
        }

        $stats = Livewire::actingAs($this->admin)
            ->test(ViewSalesEmployee::class, ['record' => $employee->getKey()])
            ->instance()
            ->stats();

        $posted = Invoice::where('sales_employee_id', $employee->getKey())
            ->where('doc_type', Invoice::TYPE_INVOICE)
            ->sum('document_total');

        $this->assertEqualsWithDelta($posted, (float) $stats['sold'], 0.01);
        $this->assertGreaterThan(0, $stats['drafts']);
    }
}
