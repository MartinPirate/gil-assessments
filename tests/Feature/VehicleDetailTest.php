<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The vehicle record, which is an operations page rather than a form.
 */
class VehicleDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function vehicleWithHistory(): Vehicle
    {
        $vehicle = Vehicle::create(['vehicle_number' => 'KDA 900Z', 'make' => 'Isuzu FRR', 'vehicle_type' => 'Truck']);
        $driver = Driver::factory()->create(['name' => 'Grace Wanjiku', 'national_id' => '30000001', 'phone' => '254700111222']);
        $route = Route::create([
            'code' => 'RT-A', 'name' => 'Nairobi - Nakuru',
            'origin' => 'Nairobi', 'destination' => 'Nakuru', 'distance_km' => 160,
        ]);

        Trip::create([
            'reference' => 'TRP-900001',
            'route_id' => $route->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
            'route_name' => $route->name, 'vehicle_number' => $vehicle->vehicle_number, 'driver_name' => $driver->name,
            'scheduled_at' => now()->subDay(), 'departed_at' => now()->subDay(), 'arrived_at' => now()->subHours(12),
            'status' => Trip::STATUS_COMPLETED,
        ]);

        return $vehicle;
    }

    public function test_the_page_shows_the_vehicle_its_driver_and_its_route(): void
    {
        $vehicle = $this->vehicleWithHistory();

        $this->actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->get(VehicleResource::getUrl('view', ['record' => $vehicle]))
            ->assertSuccessful()
            ->assertSee('KDA 900Z')
            ->assertSee('Grace Wanjiku')
            ->assertSee('Nairobi - Nakuru')
            ->assertSee('Drivers')
            ->assertSee('Routes run');
    }

    /**
     * Only completed trips have covered ground; counting a scheduled or
     * cancelled one would overstate the odometer.
     */
    public function test_distance_counts_completed_trips_only(): void
    {
        $vehicle = $this->vehicleWithHistory();

        $this->assertEqualsWithDelta(160.0, $vehicle->distance_covered, 0.01);

        Trip::where('vehicle_id', $vehicle->id)->update(['status' => Trip::STATUS_CANCELLED]);

        $this->assertEqualsWithDelta(0.0, $vehicle->fresh()->distance_covered, 0.01);
    }

    public function test_a_vehicle_with_no_history_still_renders(): void
    {
        $vehicle = Vehicle::create(['vehicle_number' => 'KDB 111B', 'make' => 'Hino']);

        $this->actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->get(VehicleResource::getUrl('view', ['record' => $vehicle]))
            ->assertSuccessful()
            ->assertSee('Never gated in')
            ->assertSee('Nobody has driven this vehicle yet.');
    }
}
