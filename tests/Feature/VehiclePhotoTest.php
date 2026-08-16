<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Database\Seeders\VehiclePhotoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Photographs on the fleet record.
 */
class VehiclePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_a_vehicle_starts_without_a_photograph(): void
    {
        $vehicle = Vehicle::create(['vehicle_number' => 'KDA 123A', 'vehicle_type' => 'Truck']);

        $this->assertFalse($vehicle->hasPhoto());
        $this->assertNull($vehicle->photo());
    }

    /**
     * Unlike a licence or a rendered invoice, a vehicle may have several — the
     * plate, the body, damage noted at the gate.
     */
    public function test_a_vehicle_keeps_several_photographs(): void
    {
        $vehicle = Vehicle::create(['vehicle_number' => 'KDB 456B', 'vehicle_type' => 'Truck']);

        $vehicle->addMedia(UploadedFile::fake()->image('front.jpg'))->toMediaCollection(Vehicle::PHOTOS);
        $vehicle->refresh();
        $vehicle->addMedia(UploadedFile::fake()->image('side.jpg'))->toMediaCollection(Vehicle::PHOTOS);

        $vehicle->refresh();

        $this->assertCount(2, $vehicle->getMedia(Vehicle::PHOTOS));
        // The first is what every screen leads with.
        $this->assertSame('front.jpg', $vehicle->photo()->file_name);
    }

    public function test_the_seeded_photographs_land_on_vehicles_of_the_matching_type(): void
    {
        $van = Vehicle::create(['vehicle_number' => 'KCX 789C', 'make' => 'Toyota Hiace', 'vehicle_type' => 'Van']);
        $car = Vehicle::create(['vehicle_number' => 'KCA 345G', 'make' => 'Toyota Probox', 'vehicle_type' => 'Car']);

        $this->seed(VehiclePhotoSeeder::class);

        $this->assertTrue($van->fresh()->hasPhoto());
        $this->assertStringContainsString('van', $van->fresh()->photo()->file_name);

        $this->assertTrue($car->fresh()->hasPhoto());
        $this->assertStringContainsString('car', $car->fresh()->photo()->file_name);
    }

    /**
     * There is no truck photograph shipped, and the seeder must leave those
     * records alone rather than putting a van on a lorry.
     */
    public function test_a_type_with_no_photograph_is_left_alone(): void
    {
        $truck = Vehicle::create(['vehicle_number' => 'KDA 123A', 'make' => 'Isuzu FRR', 'vehicle_type' => 'Truck']);

        $this->seed(VehiclePhotoSeeder::class);

        $this->assertFalse($truck->fresh()->hasPhoto());
    }

    public function test_re_running_the_seeder_does_not_stack_duplicates(): void
    {
        $van = Vehicle::create(['vehicle_number' => 'KCX 789C', 'vehicle_type' => 'Van']);

        $this->seed(VehiclePhotoSeeder::class);
        $this->seed(VehiclePhotoSeeder::class);

        $this->assertCount(1, $van->fresh()->getMedia(Vehicle::PHOTOS));
    }
}
