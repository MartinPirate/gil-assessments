<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Business partner codes are issued, not typed.
 */
class CustomerCodeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    public function test_the_first_customer_gets_the_first_code(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateCustomer::class)
            ->fillForm(['name' => 'Naivas Supermarket Ltd', 'currency' => 'KES', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('CC00001', Customer::firstOrFail()->code);
    }

    public function test_codes_run_in_sequence(): void
    {
        foreach (['Naivas', 'Quickmart', 'Carrefour'] as $name) {
            Livewire::actingAs($this->admin)
                ->test(CreateCustomer::class)
                ->fillForm(['name' => $name, 'currency' => 'KES', 'is_active' => true])
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $this->assertSame(
            ['CC00001', 'CC00002', 'CC00003'],
            Customer::orderBy('id')->pluck('code')->all(),
        );
    }

    /**
     * On a database that already has partners, the counter has to start above
     * them rather than colliding with CC00001.
     */
    public function test_it_continues_from_the_highest_existing_code(): void
    {
        Customer::create(['code' => 'CC00008', 'name' => 'Cleanshelf', 'currency' => 'KES']);

        $code = DB::transaction(fn () => app(CustomerCodeService::class)->next());

        $this->assertSame('CC00009', $code);
    }

    /**
     * The form must not offer it: a typed code would collide with the counter
     * and the next issued one would fail on the unique index.
     */
    public function test_the_code_field_cannot_be_typed_into(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateCustomer::class)
            ->assertFormFieldDisabled('code');
    }

    /**
     * Documents snapshot customer_code at posting, so a code changed later
     * would leave the register pointing at a partner nobody can look up.
     */
    public function test_the_code_cannot_be_changed_on_an_existing_customer(): void
    {
        $customer = Customer::create(['code' => 'CC00001', 'name' => 'Naivas', 'currency' => 'KES']);

        Livewire::actingAs($this->admin)
            ->test(EditCustomer::class, ['record' => $customer->getKey()])
            ->assertFormFieldDisabled('code')
            ->fillForm(['name' => 'Naivas Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $customer->refresh();

        $this->assertSame('CC00001', $customer->code);
        $this->assertSame('Naivas Renamed', $customer->name);
    }

    /**
     * The counter is locked for the length of the surrounding transaction, so
     * a batch cannot be handed the same code twice.
     */
    public function test_a_batch_of_codes_is_never_reused(): void
    {
        $codes = [];

        foreach (range(1, 12) as $ignored) {
            $codes[] = DB::transaction(fn () => app(CustomerCodeService::class)->next());
        }

        $this->assertCount(12, array_unique($codes));
        $this->assertSame('CC00001', $codes[0]);
        $this->assertSame('CC00012', $codes[11]);
    }
}
