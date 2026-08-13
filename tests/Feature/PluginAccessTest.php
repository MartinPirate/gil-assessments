<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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
    public static function fileManagerMatrix(): array
    {
        return [
            'admin may manage files' => [UserRole::Admin, true],
            'sales may manage files' => [UserRole::Sales, true],
            'approver may not' => [UserRole::Approver, false],
            'gate officer may not' => [UserRole::GateOfficer, false],
            'driver may not' => [UserRole::Driver, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fileManagerMatrix')]
    public function test_only_the_roles_that_raise_documents_may_manage_files(UserRole $role, bool $allowed): void
    {
        $user = User::factory()->role($role)->create();

        $this->assertSame($allowed, Gate::forUser($user)->allows('manageFileManager'));
    }

    /**
     * @return array<string, array{UserRole, bool}>
     */
    public static function commandCentreMatrix(): array
    {
        return [
            'admin' => [UserRole::Admin, true],
            'sales' => [UserRole::Sales, false],
            'approver' => [UserRole::Approver, false],
            'gate officer' => [UserRole::GateOfficer, false],
            'driver' => [UserRole::Driver, false],
        ];
    }

    /**
     * Running artisan commands from the browser is deploy-level authority, so
     * every one of the module's abilities stops at the administrator.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('commandCentreMatrix')]
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
            'file manager' => ['admin/file-manager'],
            'changelog' => ['admin/changelog'],
            'command centre' => ['admin/command-center/commands'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('administratorPages')]
    public function test_an_administrator_can_open_each_plugin_page(string $path): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());

        $this->get($path)->assertSuccessful();
    }

    /**
     * The same pages must not open for a driver, the narrowest role.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('administratorPages')]
    public function test_a_driver_is_refused_every_system_page(string $path): void
    {
        // The changelog reader is deliberately open to everyone.
        if ($path === 'admin/changelog') {
            $this->markTestSkipped('The changelog reader is intentionally readable by all roles.');
        }

        $this->actingAs(User::factory()->role(UserRole::Driver)->create());

        $this->get($path)->assertForbidden();
    }

    public function test_every_plugin_is_registered_on_the_panel(): void
    {
        $registered = array_keys(\Filament\Facades\Filament::getPanel('admin')->getPlugins());

        foreach ([
            'filament-file-manager',
            'filament-autosave',
            'formsettings-for-filament',
            'filament-column-filters',
            'filament-odometer-easy',
            'filament-mobile-preset',
            'launchpad',
            'filament-onboarding',
            'filament-notification-center',
            'filament-activity-timeline',
            'filament-logs-explorer',
            'filament-dependency-graph',
            'command-center',
            'changelog',
            'filament-openapi-docs',
        ] as $plugin) {
            $this->assertContains($plugin, $registered, "Plugin [{$plugin}] is not registered.");
        }
    }
}
