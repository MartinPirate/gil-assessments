<?php

namespace Tests\Feature;

use App\Filament\Pages\VehicleGateIn;
use App\Filament\Pages\VehicleGateOut;
use App\Models\Driver;
use App\Models\GateLog;
use App\Models\LoginSession;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GateService;
use App\Support\ChooseFromListRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 2 — gate operations and session capture.
 */
class GateOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Vehicle $vehicle;

    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);

        // Gate screens are gated to the gate-officer role.
        $this->user = User::factory()->gateOfficer()->create();

        $this->vehicle = Vehicle::create([
            'vehicle_number' => 'KDA 123A',
            'make' => 'Isuzu FRR',
            'vehicle_type' => 'Truck',
        ]);

        $this->driver = Driver::create([
            'name' => 'John Mwangi Kamau',
            'national_id' => '23456789',
            'phone' => '0722345678',
        ]);
    }

    public function test_gate_in_records_time_and_user_automatically(): void
    {
        Livewire::actingAs($this->user)
            ->test(VehicleGateIn::class)
            ->fillForm([
                'vehicle_id' => $this->vehicle->id,
                'driver_id' => $this->driver->id,
                'driver_national_id' => $this->driver->national_id,
                'driver_phone' => $this->driver->phone,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $log = GateLog::firstOrFail();

        $this->assertSame('KDA 123A', $log->vehicle_number);
        $this->assertSame('John Mwangi Kamau', $log->driver_name);
        $this->assertSame('23456789', $log->driver_national_id);
        $this->assertSame(GateLog::STATUS_IN, $log->status);
        $this->assertNotNull($log->time_in);
        $this->assertEquals($this->user->id, $log->gated_in_by);
        $this->assertNull($log->time_out);
    }

    public function test_selecting_a_driver_populates_id_and_phone(): void
    {
        Livewire::actingAs($this->user)
            ->test(VehicleGateIn::class)
            ->set('data.driver_id', $this->driver->id)
            ->assertSet('data.driver_national_id', '23456789')
            ->assertSet('data.driver_phone', '0722345678');
    }

    public function test_a_vehicle_cannot_be_gated_in_twice(): void
    {
        $service = app(GateService::class);
        $service->gateIn([
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
        ], $this->user->id);

        $this->expectException(ValidationException::class);

        $service->gateIn([
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
        ], $this->user->id);
    }

    /**
     * Task 2c: the Gate Out screen must not offer the whole fleet.
     */
    public function test_gate_out_only_lists_vehicles_currently_inside(): void
    {
        $other = Vehicle::create(['vehicle_number' => 'KDB 456B', 'make' => 'Fuso']);

        app(GateService::class)->gateIn([
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
        ], $this->user->id);

        $options = ChooseFromListRegistry::search('vehicles_gated_in', 'KD');

        $this->assertArrayHasKey($this->vehicle->id, $options);
        $this->assertArrayNotHasKey($other->id, $options);

        // The unfiltered list still contains both.
        $all = ChooseFromListRegistry::search('vehicles', 'KD');
        $this->assertArrayHasKey($other->id, $all);
    }

    public function test_gate_out_populates_driver_details_from_the_open_record(): void
    {
        app(GateService::class)->gateIn([
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
        ], $this->user->id);

        Livewire::actingAs($this->user)
            ->test(VehicleGateOut::class)
            ->set('data.vehicle_id', $this->vehicle->id)
            ->assertSet('data.driver_name', 'John Mwangi Kamau')
            ->assertSet('data.driver_national_id', '23456789')
            ->assertSet('data.driver_phone', '0722345678');
    }

    public function test_gate_out_closes_the_record(): void
    {
        app(GateService::class)->gateIn([
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
        ], $this->user->id);

        $officer = User::factory()->gateOfficer()->create();

        Livewire::actingAs($officer)
            ->test(VehicleGateOut::class)
            ->fillForm(['vehicle_id' => $this->vehicle->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $log = GateLog::firstOrFail();

        $this->assertSame(GateLog::STATUS_OUT, $log->status);
        $this->assertNotNull($log->time_out);
        $this->assertEquals($officer->id, $log->gated_out_by);
        // The original gate-in user is preserved, not overwritten.
        $this->assertEquals($this->user->id, $log->gated_in_by);
    }

    public function test_a_vehicle_that_is_not_inside_cannot_be_gated_out(): void
    {
        $this->expectException(ValidationException::class);

        app(GateService::class)->gateOut($this->vehicle->id, $this->user->id);
    }

    public function test_the_same_vehicle_can_return_after_leaving(): void
    {
        $service = app(GateService::class);

        $service->gateIn(['vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id], $this->user->id);
        $service->gateOut($this->vehicle->id, $this->user->id);
        $service->gateIn(['vehicle_id' => $this->vehicle->id, 'driver_id' => $this->driver->id], $this->user->id);

        $this->assertSame(2, GateLog::count());
        $this->assertSame(1, GateLog::open()->count());
    }

    /**
     * Task 2a: login timestamps and sessions are persisted.
     */
    public function test_logging_in_records_a_session(): void
    {
        // Filament's login is a Livewire component, not a plain POST route,
        // so drive the real page to prove the listener fires in production.
        Livewire::test(\Filament\Auth\Pages\Login::class)
            ->fillForm([
                'email' => $this->user->email,
                'password' => 'password',
            ])
            ->call('authenticate');

        $this->assertAuthenticatedAs($this->user);

        $session = LoginSession::where('user_id', $this->user->id)->first();

        $this->assertNotNull($session, 'A login session should have been recorded.');
        $this->assertNotNull($session->logged_in_at);
        $this->assertNull($session->logged_out_at);
    }

    public function test_logging_out_closes_the_session(): void
    {
        $this->actingAs($this->user);

        event(new \Illuminate\Auth\Events\Login('web', $this->user, false));
        event(new \Illuminate\Auth\Events\Logout('web', $this->user));

        $session = LoginSession::where('user_id', $this->user->id)->latest('id')->first();

        $this->assertNotNull($session->logged_out_at);
    }
}
