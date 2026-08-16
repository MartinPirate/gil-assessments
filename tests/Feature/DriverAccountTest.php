<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Drivers\Pages\CreateDriver;
use App\Filament\Resources\Drivers\Pages\EditDriver;
use App\Models\Driver;
use App\Models\GateLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every driver is a user.
 */
class DriverAccountTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    public function test_a_driver_cannot_be_stored_without_an_account(): void
    {
        $this->expectException(QueryException::class);

        Driver::create(['name' => 'No Login', 'national_id' => '800', 'phone' => '0700000800']);
    }

    public function test_creating_a_driver_creates_the_login_with_it(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateDriver::class)
            ->fillForm([
                'name' => 'Michael Kiprop',
                'national_id' => '25678901',
                'phone' => '0711567890',
                'email' => 'michael.kiprop@gil.test',
                'password' => 'Str0ng-Passw0rd!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $driver = Driver::where('national_id', '25678901')->firstOrFail();
        $user = $driver->user;

        $this->assertNotNull($user);
        $this->assertSame('michael.kiprop@gil.test', $user->email);
        $this->assertSame(UserRole::Driver, $user->role());
        $this->assertSame('Michael Kiprop', $user->name);
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!', $user->password));
    }

    /**
     * The driver row goes in after the user row. If it fails, the account it
     * would have belonged to must not be left behind.
     */
    public function test_a_rejected_driver_leaves_no_orphaned_account(): void
    {
        $existing = Driver::factory()->create(['national_id' => '777']);

        try {
            Livewire::actingAs($this->admin)
                ->test(CreateDriver::class)
                ->fillForm([
                    'name' => 'Duplicate ID',
                    // Collides with the driver above, so the insert fails.
                    'national_id' => '777',
                    'phone' => '0700000777',
                    'email' => 'orphan.check@gil.test',
                    'password' => 'Str0ng-Passw0rd!',
                ])
                ->call('create');

            $this->fail('The duplicate driver ID should have been rejected.');
        } catch (QueryException) {
            // Expected — the point is what the database looks like afterwards.
        }

        $this->assertNull(User::where('email', 'orphan.check@gil.test')->first());
        $this->assertSame(1, Driver::where('national_id', '777')->count());
        $this->assertTrue($existing->exists);
    }

    public function test_renaming_the_driver_renames_the_login(): void
    {
        $driver = Driver::factory()->create(['name' => 'Old Name']);

        $driver->update(['name' => 'New Name']);

        $this->assertSame('New Name', $driver->user()->first()->name);
    }

    public function test_renaming_the_login_renames_the_driver(): void
    {
        $driver = Driver::factory()->create(['name' => 'Old Name']);

        $driver->user->update(['name' => 'Corrected Name']);

        $this->assertSame('Corrected Name', $driver->fresh()->name);
    }

    public function test_a_user_with_no_driver_record_renames_without_incident(): void
    {
        $user = User::factory()->role(UserRole::Sales)->create(['name' => 'Mercy Nyambura']);

        $user->update(['name' => 'Mercy N.']);

        $this->assertSame('Mercy N.', $user->fresh()->name);
    }

    /**
     * A gate log keeps only driver_id, so a correction to the driver master
     * shows through everywhere it has ever appeared.
     */
    public function test_a_gate_log_follows_the_drivers_current_name(): void
    {
        $driver = Driver::factory()->create(['name' => 'John Mwangi']);
        $vehicle = Vehicle::create(['vehicle_number' => 'KDA 123A']);

        $log = GateLog::create([
            'vehicle_id' => $vehicle->getKey(),
            'driver_id' => $driver->getKey(),
            'time_in' => now(),
            'status' => GateLog::STATUS_IN,
            'gated_in_by' => $this->admin->getKey(),
        ]);

        $driver->update(['name' => 'John Mwangi Kamau']);

        $this->assertSame('John Mwangi Kamau', $log->fresh()->driver->name);
        $this->assertSame('KDA 123A', $log->vehicle->vehicle_number);
    }

    /**
     * With no snapshot left, the foreign key is what stops a gate log from
     * losing the person it is about.
     */
    public function test_a_driver_who_has_been_through_the_gate_cannot_be_deleted(): void
    {
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::create(['vehicle_number' => 'KDB 456B']);

        GateLog::create([
            'vehicle_id' => $vehicle->getKey(),
            'driver_id' => $driver->getKey(),
            'time_in' => now(),
            'status' => GateLog::STATUS_IN,
            'gated_in_by' => $this->admin->getKey(),
        ]);

        $this->expectException(QueryException::class);

        $driver->delete();
    }

    public function test_editing_a_driver_updates_the_login_email(): void
    {
        $driver = Driver::factory()->create(['name' => 'Stephen Odhiambo']);

        Livewire::actingAs($this->admin)
            ->test(EditDriver::class, ['record' => $driver->getKey()])
            ->fillForm(['email' => 'stephen.new@gil.test'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('stephen.new@gil.test', $driver->user()->first()->email);
    }

    public function test_a_blank_password_on_edit_leaves_it_untouched(): void
    {
        $driver = Driver::factory()->create();
        $before = $driver->user->password;

        Livewire::actingAs($this->admin)
            ->test(EditDriver::class, ['record' => $driver->getKey()])
            ->fillForm(['phone' => '0700111222', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($before, $driver->user()->first()->password);
        $this->assertSame('0700111222', $driver->fresh()->phone);
    }
}
