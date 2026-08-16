<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Routes\Pages\EditRoute;
use App\Models\Route;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Routes on a map, and the distance that follows from it.
 */
class RouteMapTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);

        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    protected function nairobiToMombasa(): Route
    {
        return Route::create([
            'code' => 'RT-NBO-MSA',
            'name' => 'Nairobi to Mombasa',
            'origin' => 'Nairobi',
            'destination' => 'Mombasa',
            'origin_latitude' => -1.2921,
            'origin_longitude' => 36.8219,
            'destination_latitude' => -4.0435,
            'destination_longitude' => 39.6682,
        ]);
    }

    public function test_a_route_with_both_ends_pinned_can_be_drawn(): void
    {
        $route = $this->nairobiToMombasa();

        $this->assertTrue($route->isMappable());
    }

    public function test_a_route_missing_a_pin_is_not_mappable(): void
    {
        $route = Route::create([
            'code' => 'RT-X',
            'name' => 'Unpinned',
            'origin' => 'Somewhere',
            'destination' => 'Elsewhere',
            'origin_latitude' => -1.2921,
            'origin_longitude' => 36.8219,
        ]);

        $this->assertFalse($route->isMappable());
        $this->assertNull($route->greatCircleKm());
    }

    /**
     * Nairobi to Mombasa is about 440 km as the crow flies; the road is 485,
     * which is why the figure is offered rather than written over.
     */
    public function test_the_straight_line_distance_is_computed_from_the_pins(): void
    {
        $km = $this->nairobiToMombasa()->greatCircleKm();

        $this->assertEqualsWithDelta(440, $km, 15);
        $this->assertLessThan(485, $km, 'The straight line must be shorter than the road.');
    }

    public function test_the_map_and_its_tiles_are_served_with_the_form(): void
    {
        $route = $this->nairobiToMombasa();

        $html = Livewire::actingAs($this->admin)
            ->test(EditRoute::class, ['record' => $route->getKey()])
            ->html();

        $this->assertStringContainsString('sapRouteMap', $html);
        $this->assertStringContainsString('route-map__canvas', $html);
        // Free tiles, no API key anywhere in the page.
        $this->assertStringContainsString('OpenStreetMap', $html);
    }

    public function test_the_coordinate_fields_round_trip(): void
    {
        $route = $this->nairobiToMombasa();

        Livewire::actingAs($this->admin)
            ->test(EditRoute::class, ['record' => $route->getKey()])
            ->fillForm([
                'origin_latitude' => -0.0917,
                'origin_longitude' => 34.7680,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsWithDelta(-0.0917, (float) $route->fresh()->origin_latitude, 0.0001);
    }

    /**
     * A latitude of 500 is not a place.
     */
    public function test_coordinates_outside_the_globe_are_rejected(): void
    {
        $route = $this->nairobiToMombasa();

        Livewire::actingAs($this->admin)
            ->test(EditRoute::class, ['record' => $route->getKey()])
            ->fillForm(['origin_latitude' => 500])
            ->call('save')
            ->assertHasFormErrors(['origin_latitude']);
    }

    /**
     * A journey takes nine and a half hours, not nine or ten. The column was
     * an integer while the form offered a plain numeric field, so 9.5 reached
     * SQL Server as a string it refused to convert — a 500, not a validation
     * message.
     */
    public function test_a_route_accepts_fractional_hours(): void
    {
        $route = $this->nairobiToMombasa();

        Livewire::actingAs($this->admin)
            ->test(EditRoute::class, ['record' => $route->getKey()])
            ->fillForm(['estimated_hours' => 9.5])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsWithDelta(9.5, (float) $route->fresh()->estimated_hours, 0.001);
    }
}
