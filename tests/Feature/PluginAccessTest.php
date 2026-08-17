<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The installed Filament plugins ask the application who may use them.
 *
 * Without these gates the file browser and the command runner would be open to
 * every signed-in user, so the answers are pinned here the same way the rest of
 * the role matrix is.
 */
class PluginAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{UserRole, bool}>
     */
    public static function commandCentreMatrix(): array
    {
        return [
            'admin' => [UserRole::Admin, true],
            'sales' => [UserRole::Sales, false],
            'approver' => [UserRole::Manager, false],
            'gate officer' => [UserRole::GateOfficer, false],
            'driver' => [UserRole::Driver, false],
        ];
    }

    /**
     * Running artisan commands from the browser is deploy-level authority, so
     * every one of the module's abilities stops at the administrator.
     */
    #[DataProvider('commandCentreMatrix')]
    public function test_only_an_administrator_reaches_the_command_centre(UserRole $role, bool $allowed): void
    {
        $user = User::factory()->role($role)->create();

        foreach ([
            'command-center:access',
            'command-center:prune-history',
            'command-center:manage-commands',
        ] as $ability) {
            $this->assertSame(
                $allowed,
                Gate::forUser($user)->allows($ability),
                "Ability [{$ability}] was wrong for role [{$role->value}]."
            );
        }
    }

    /**
     * Registering a plugin is not the same as it working. These are the pages
     * the plugins add; an administrator should get all of them back.
     *
     * @return array<string, array{string}>
     */
    public static function administratorPages(): array
    {
        return [
            'logs explorer' => ['admin/logs'],
            'dependency graph' => ['admin/dependency-graph'],
            'changelog' => ['admin/changelog'],
            'command centre' => ['admin/command-center/commands'],
        ];
    }

    #[DataProvider('administratorPages')]
    public function test_an_administrator_can_open_each_plugin_page(string $path): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->get($path)->assertSuccessful();
    }

    /**
     * The same pages must not open for a driver, the narrowest role.
     */
    #[DataProvider('administratorPages')]
    public function test_a_driver_is_refused_every_system_page(string $path): void
    {
        $this->actingAs(User::factory()->role(UserRole::Driver)->create());

        $this->get($path)->assertForbidden();
    }

    public function test_every_plugin_is_registered_on_the_panel(): void
    {
        $registered = array_keys(Filament::getPanel('admin')->getPlugins());

        foreach ([
            'filament-autosave',
            'formsettings-for-filament',
            'filament-column-filters',
            'filament-odometer-easy',
            'filament-mobile-preset',
            'filament-onboarding',
            'filament-notification-center',
            'filament-activity-timeline',
            'filament-logs-explorer',
            'filament-dependency-graph',
            'command-center',
            'changelog',
        ] as $plugin) {
            $this->assertContains($plugin, $registered, "Plugin [{$plugin}] is not registered.");
        }
    }
}
