<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\MyTrips;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The driver portal is a security boundary: a driver signs in and must see
 * and act on their own work only.
 */
class DriverPortalTest extends TestCase
{
    use RefreshDatabase;

    protected Driver $driver;

    protected Driver $otherDriver;

    protected User $driverUser;

    protected Route $route;

    protected Vehicle $vehicle;

    protected Vehicle $otherVehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driverUser = User::factory()->role(UserRole::Driver)->create();

        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id,
            'name' => 'John Mwangi', 'national_id' => '111', 'phone' => '0700000001',
        ]);

        $this->otherDriver = Driver::create([
            'name' => 'Someone Else', 'national_id' => '222', 'phone' => '0700000002',
        ]);

        $this->route = Route::create([
            'code' => 'RT-1', 'name' => 'Nairobi to Mombasa',
            'origin' => 'Nairobi', 'destination' => 'Mombasa',
        ]);

        $this->vehicle = Vehicle::create(['vehicle_number' => 'KDA 111A']);
        $this->otherVehicle = Vehicle::create(['vehicle_number' => 'KDB 222B']);
    }

    protected function tripFor(Driver $driver, Vehicle $vehicle, string $status = Trip::STATUS_SCHEDULED): Trip
    {
        return Trip::create([
            'reference' => 'TRP-'.$driver->id.$vehicle->id.substr($status, 0, 2),
            'route_id' => $this->route->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'route_name' => $this->route->name,
            'vehicle_number' => $vehicle->vehicle_number,
            'driver_name' => $driver->name,
            'scheduled_at' => now()->addHour(),
            'status' => $status,
            'departed_at' => $status === Trip::STATUS_IN_TRANSIT ? now()->subHour() : null,
        ]);
    }

    public function test_a_driver_sees_only_their_own_trips(): void
    {
        $mine = $this->tripFor($this->driver, $this->vehicle);
        $theirs = $this->tripFor($this->otherDriver, $this->otherVehicle);

        $trips = Livewire::actingAs($this->driverUser)
            ->test(MyTrips::class)
            ->instance()
            ->getTrips();

        $this->assertTrue($trips->contains('id', $mine->id));
        $this->assertFalse($trips->contains('id', $theirs->id), 'A driver must not see another driver\'s trip.');
    }

    /**
     * The trip id travels from the browser, so ownership is re-checked on the
     * server rather than trusted.
     */
    public function test_a_driver_cannot_act_on_another_drivers_trip(): void
    {
        $theirs = $this->tripFor($this->otherDriver, $this->otherVehicle, Trip::STATUS_SCHEDULED);

        $page = Livewire::actingAs($this->driverUser)->test(MyTrips::class);

        try {
            $page->callAction('startTrip', arguments: ['trip' => $theirs->id]);
        } catch (ModelNotFoundException) {
            // Expected: scoping happens before the key lookup.
        }

        $this->assertSame(Trip::STATUS_SCHEDULED, $theirs->fresh()->status);
        $this->assertNull($theirs->fresh()->departed_at);
    }

    public function test_a_driver_can_start_and_complete_their_own_trip(): void
    {
        $trip = $this->tripFor($this->driver, $this->vehicle);

        $page = Livewire::actingAs($this->driverUser)->test(MyTrips::class);

        $page->callAction('startTrip', arguments: ['trip' => $trip->id]);
        $this->assertSame(Trip::STATUS_IN_TRANSIT, $trip->fresh()->status);
        $this->assertNotNull($trip->fresh()->departed_at);

        $page->callAction('completeTrip', arguments: ['trip' => $trip->id]);
        $this->assertSame(Trip::STATUS_COMPLETED, $trip->fresh()->status);
        $this->assertNotNull($trip->fresh()->arrived_at);
    }

    /**
     * A login with no linked driver record must show nothing, never everything.
     */
    public function test_an_unlinked_driver_account_cannot_reach_the_page(): void
    {
        $orphan = User::factory()->role(UserRole::Driver)->create();

        $this->actingAs($orphan);

        $this->assertNull($orphan->driverId());
        $this->assertFalse(MyTrips::canAccess());
    }

    public function test_non_drivers_cannot_reach_the_page(): void
    {
        foreach ([UserRole::Sales, UserRole::Approver, UserRole::GateOfficer, UserRole::Admin] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->assertFalse(MyTrips::canAccess(), "{$role->value} should not reach the driver portal.");
        }
    }

    public function test_the_scope_matches_nothing_for_a_null_driver(): void
    {
        $this->tripFor($this->driver, $this->vehicle);

        $this->assertSame(0, Trip::query()->forDriver(null)->count());
    }

    public function test_the_badge_counts_only_open_trips_for_that_driver(): void
    {
        $this->tripFor($this->driver, $this->vehicle, Trip::STATUS_SCHEDULED);
        $this->tripFor($this->driver, $this->otherVehicle, Trip::STATUS_COMPLETED);
        $this->tripFor($this->otherDriver, $this->otherVehicle, Trip::STATUS_SCHEDULED);

        $this->actingAs($this->driverUser);

        $this->assertSame('1', MyTrips::getNavigationBadge());
    }

    /* -----------------------------------------------------------------
     | Trip lifecycle
     | ----------------------------------------------------------------- */

    public function test_a_completed_trip_cannot_go_backwards(): void
    {
        $trip = $this->tripFor($this->driver, $this->vehicle, Trip::STATUS_COMPLETED);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TripService::class)->depart($trip);
    }

    public function test_a_vehicle_cannot_be_double_booked(): void
    {
        $this->tripFor($this->driver, $this->vehicle, Trip::STATUS_SCHEDULED);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TripService::class)->schedule([
            'route_id' => $this->route->id,
            'vehicle_id' => $this->vehicle->id,      // already committed
            'driver_id' => $this->otherDriver->id,
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function test_a_driver_cannot_be_double_booked(): void
    {
        $this->tripFor($this->driver, $this->vehicle, Trip::STATUS_IN_TRANSIT);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TripService::class)->schedule([
            'route_id' => $this->route->id,
            'vehicle_id' => $this->otherVehicle->id,
            'driver_id' => $this->driver->id,        // already driving
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function test_scheduling_snapshots_the_route_vehicle_and_driver(): void
    {
        $trip = app(TripService::class)->schedule([
            'route_id' => $this->route->id,
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->driver->id,
            'scheduled_at' => now()->addDay(),
            'cargo_description' => '400 bales',
        ], $this->driverUser->id);

        $this->assertSame('Nairobi to Mombasa', $trip->route_name);
        $this->assertSame('KDA 111A', $trip->vehicle_number);
        $this->assertSame('John Mwangi', $trip->driver_name);
        $this->assertStringStartsWith('TRP-', $trip->reference);
    }
}
