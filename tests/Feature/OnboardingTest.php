<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;
use Database\Seeders\OnboardingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Wallacemartinss\FilamentOnboarding\Models\OnboardingStep;
use Wallacemartinss\FilamentOnboarding\OnboardingManager;

/**
 * The first-day checklists, and who is shown which.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OnboardingSeeder::class);
    }

    /**
     * The conditions are registered by the plugin when the panel boots, so the
     * question has to be asked from inside a request rather than from a bare
     * container.
     *
     * @return array<int, string>
     */
    protected function flowsVisibleTo(User $user): array
    {
        $keys = [];

        /*
         * Followed rather than asserted successful: the panel root sends a
         * gate officer and a driver on to the screen they work from, because
         * neither has a dashboard to land on. Either way the panel has booted
         * by the time the response arrives, which is all this needs.
         */
        $this->actingAs($user)->followingRedirects()->get('/admin')->assertSuccessful();

        $keys = app(OnboardingManager::class)
            ->for($user)
            ->flows('admin')
            ->map(fn ($state) => $state->flow->key)
            ->all();

        return array_values($keys);
    }

    public function test_each_role_is_shown_its_own_journey(): void
    {
        $expected = [
            UserRole::Sales->value => 'sales-first-day',
            UserRole::Manager->value => 'manager-first-day',
            UserRole::GateOfficer->value => 'gate-first-day',
        ];

        foreach ($expected as $role => $key) {
            $user = User::factory()->role(UserRole::from($role))->create();

            $this->assertSame([$key], $this->flowsVisibleTo($user), "role {$role}");
        }
    }

    /**
     * A driver must not be walked through screens their role cannot open.
     */
    public function test_a_driver_sees_only_the_driver_journey(): void
    {
        $driver = Driver::factory()->create();

        $this->assertSame(['driver-first-day'], $this->flowsVisibleTo($driver->user));
    }

    /**
     * The administrator can reach everything, so every journey applies.
     */
    public function test_an_administrator_sees_the_journeys_their_role_covers(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $visible = $this->flowsVisibleTo($admin);

        $this->assertContains('admin-first-day', $visible);
        $this->assertNotContains('driver-first-day', $visible);
    }

    /**
     * Steps complete by visiting the screen they name, so the path has to be
     * the panel-relative one the browser will actually be on.
     */
    public function test_steps_point_at_real_panel_paths(): void
    {
        $steps = OnboardingStep::all();

        $this->assertNotEmpty($steps);

        foreach ($steps as $step) {
            $this->assertStringStartsWith('/admin/', $step->visit_url, $step->title);
            $this->assertSame($step->visit_url, $step->cta_url, $step->title);
        }
    }
}
