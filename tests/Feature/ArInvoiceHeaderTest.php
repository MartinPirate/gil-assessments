<?php

namespace Tests\Feature;

use App\Filament\Pages\ArInvoice;
use App\Models\Customer;
use App\Models\User;
use App\Support\ChooseFromListRegistry;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The business-partner block at the top of the document.
 */
class ArInvoiceHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

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
    }

    /**
     * The Customer box carries the code and the Name box the name. Joining
     * both into either one overflowed the field and truncated the value.
     */
    public function test_each_lookup_shows_only_its_own_column(): void
    {
        $id = $this->customer->getKey();

        $this->assertSame('CC00001', ChooseFromListRegistry::optionLabel('customers_by_code', $id));
        $this->assertSame('Walk In Customer - HQ', ChooseFromListRegistry::optionLabel('customers_by_name', $id));

        // The search list still needs both, to tell rows apart.
        $this->assertStringContainsString('CC00001', ChooseFromListRegistry::optionLabel('customers_by_code', $id, false));
        $this->assertStringContainsString('Walk In Customer - HQ', ChooseFromListRegistry::optionLabel('customers_by_code', $id, false));
    }

    /**
     * A failed save leaves "the customer field is required" on screen. Picking
     * a partner afterwards — from either box — has to take it away.
     */
    public function test_choosing_a_customer_clears_the_stale_required_error(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->call('save')
            ->assertHasFormErrors(['customer_id']);

        $component->set('data.customer_name_lookup', $this->customer->getKey())
            ->assertHasNoFormErrors(['customer_id']);

        // pdo_sqlsrv hands bigints back as strings, so compare loosely.
        $this->assertEquals($this->customer->getKey(), $component->instance()->data['customer_id']);
    }

    public function test_picking_from_the_name_box_fills_the_code_box_too(): void
    {
        Livewire::actingAs($this->user)
            ->test(ArInvoice::class)
            ->set('data.customer_name_lookup', $this->customer->getKey())
            ->assertSet('data.customer_id', $this->customer->getKey())
            ->assertSet('data.customer_display_name', 'Walk In Customer - HQ')
            ->assertSet('data.contact_person', 'TEST TEST');
    }
}
