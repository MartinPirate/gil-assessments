<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lock screen.
 *
 * The gate terminal is shared and sits unattended between trucks, so this is
 * a security control rather than a convenience: whoever is signed in there can
 * raise invoices and admit vehicles.
 */
class LockscreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_plugin_is_registered(): void
    {
        $this->assertNotNull(
            Filament::getPanel('admin')->getPlugin('filament-lockscreen'),
            'The lockscreen plugin should be registered on the admin panel.',
        );
    }

    /**
     * The plugin injects its own middleware ahead of Filament's authentication.
     * If that ordering ever changes, an unauthenticated request could reach the
     * locker before it is known who — if anyone — is signed in.
     */
    public function test_the_locker_middleware_guards_the_panel(): void
    {
        $middleware = Filament::getPanel('admin')->getAuthMiddleware();

        $names = array_map(
            fn ($entry): string => is_array($entry) ? $entry[0] : $entry,
            $middleware,
        );

        $this->assertContains(\lockscreen\FilamentLockscreen\Http\Middleware\Locker::class, $names);
    }

    public function test_an_anonymous_visitor_cannot_reach_the_lock_screen(): void
    {
        $this->get('admin/screen/lock')->assertRedirect();
    }

    /**
     * The lock screen only exists for a session that is actually locked.
     *
     * Asking for it while unlocked is bounced back into the panel, which is
     * what stops it becoming a page anyone can sit on.
     */
    public function test_the_lock_screen_is_only_reachable_once_locked(): void
    {
        $user = User::factory()->role(UserRole::GateOfficer)->create();

        $this->actingAs($user)
            ->get('admin/screen/lock')
            ->assertRedirect();

        $this->actingAs($user)->post('admin/lock-session');

        $this->actingAs($user)
            ->get('admin/screen/lock')
            ->assertSuccessful();
    }

    /**
     * And once locked, the rest of the panel is closed off — otherwise the
     * lock would be decorative.
     */
    public function test_a_locked_session_cannot_reach_the_panel(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($user)->post('admin/lock-session');

        $this->actingAs($user)
            ->get('admin/dashboard')
            ->assertRedirect();
    }

    /**
     * Locking must not sign the user out — the point is that the same person
     * unlocks with their password and lands back where they were.
     */
    public function test_locking_keeps_the_session_signed_in(): void
    {
        $user = User::factory()->role(UserRole::GateOfficer)->create();

        $this->actingAs($user)->post('admin/lock-session');

        $this->assertAuthenticatedAs($user);
    }

    /**
     * The panel still works normally for a session that is not locked; the
     * middleware must not stand between a signed-in user and their screens.
     */
    public function test_an_unlocked_session_reaches_the_panel(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->get('admin/dashboard')->assertSuccessful();
    }
}
