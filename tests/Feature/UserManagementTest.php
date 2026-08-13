<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Administering user accounts.
 *
 * This screen hands out authority, so the guards around it matter as much as
 * the CRUD itself.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    public function test_only_administrators_can_manage_users(): void
    {
        foreach ([UserRole::Sales, UserRole::Approver, UserRole::GateOfficer, UserRole::Driver] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->assertFalse(UserResource::canAccess(), "{$role->value} must not manage users.");
        }

        $this->actingAs($this->admin);
        $this->assertTrue(UserResource::canAccess());
    }

    public function test_an_admin_can_create_a_user(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Mercy Nyambura',
                'email' => 'mercy@gil.test',
                'password' => 'Str0ng-Passw0rd!',
                'role' => UserRole::Sales->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'mercy@gil.test')->firstOrFail();

        $this->assertSame(UserRole::Sales, $user->role);
        $this->assertTrue($user->is_active);
    }

    /**
     * The password must be hashed on the way in, never stored as typed.
     */
    public function test_passwords_are_hashed(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Hash Me',
                'email' => 'hash@gil.test',
                'password' => 'Str0ng-Passw0rd!',
                'role' => UserRole::Sales->value,
            ])
            ->call('create');

        $user = User::where('email', 'hash@gil.test')->firstOrFail();

        $this->assertNotSame('Str0ng-Passw0rd!', $user->password);
        $this->assertTrue(Hash::check('Str0ng-Passw0rd!', $user->password));
    }

    /**
     * Leaving the password blank on edit must keep the existing one rather
     * than blanking it.
     */
    public function test_an_empty_password_on_edit_leaves_it_untouched(): void
    {
        $user = User::factory()->sales()->create();
        $original = $user->password;

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['name' => 'Renamed', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Renamed', $user->name);
        $this->assertSame($original, $user->password);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@gil.test']);

        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Clash',
                'email' => 'taken@gil.test',
                'password' => 'Str0ng-Passw0rd!',
                'role' => UserRole::Sales->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    public function test_a_weak_password_is_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Weak',
                'email' => 'weak@gil.test',
                'password' => '123',
                'role' => UserRole::Sales->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    /**
     * An admin who demotes or deactivates their own account would lock
     * themselves out with no way back in.
     */
    public function test_an_admin_cannot_change_their_own_role_or_status(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $this->admin->getKey()]);

        $component->assertFormFieldDisabled('role');
        $component->assertFormFieldDisabled('is_active');
    }

    public function test_an_admin_can_change_another_users_role(): void
    {
        $other = User::factory()->sales()->create();

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $other->getKey()])
            ->fillForm(['role' => UserRole::Approver->value, 'approval_limit' => 25000])
            ->call('save')
            ->assertHasNoFormErrors();

        $other->refresh();

        $this->assertSame(UserRole::Approver, $other->role);
        $this->assertEquals(25000, $other->approval_limit);
    }

    /* -----------------------------------------------------------------
     | Driver linking
     | ----------------------------------------------------------------- */

    public function test_creating_a_driver_account_links_the_driver_record(): void
    {
        $driver = Driver::create(['name' => 'John Mwangi', 'national_id' => '900', 'phone' => '0700900900']);

        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'John Mwangi',
                'email' => 'john.driver@gil.test',
                'password' => 'Str0ng-Passw0rd!',
                'role' => UserRole::Driver->value,
                'driver_id' => $driver->getKey(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'john.driver@gil.test')->firstOrFail();

        $this->assertEquals($user->getKey(), $driver->fresh()->user_id);
        $this->assertEquals($driver->getKey(), $user->driverId());
    }

    /**
     * Re-pointing a login at a different driver must release the old one, or a
     * driver record would end up attached to two accounts.
     */
    public function test_relinking_releases_the_previous_driver(): void
    {
        $first = Driver::create(['name' => 'First', 'national_id' => '901', 'phone' => '0700000901']);
        $second = Driver::create(['name' => 'Second', 'national_id' => '902', 'phone' => '0700000902']);

        $user = User::factory()->role(UserRole::Driver)->create();
        $first->update(['user_id' => $user->getKey()]);

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['driver_id' => $second->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($first->fresh()->user_id);
        $this->assertEquals($user->getKey(), $second->fresh()->user_id);
    }

    public function test_changing_a_driver_to_another_role_releases_the_link(): void
    {
        $driver = Driver::create(['name' => 'Ex Driver', 'national_id' => '903', 'phone' => '0700000903']);
        $user = User::factory()->role(UserRole::Driver)->create();
        $driver->update(['user_id' => $user->getKey()]);

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['role' => UserRole::GateOfficer->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($driver->fresh()->user_id);
    }

    /* -----------------------------------------------------------------
     | Sign-in
     | ----------------------------------------------------------------- */

    public function test_a_deactivated_user_cannot_reach_the_panel(): void
    {
        $panel = \Filament\Facades\Filament::getPanel('admin');

        $this->assertFalse(User::factory()->inactive()->create()->canAccessPanel($panel));
        $this->assertTrue(User::factory()->create()->canAccessPanel($panel));
    }

    /**
     * User changes are consequential, so they belong in the audit trail.
     */
    public function test_user_changes_are_audited(): void
    {
        $user = User::factory()->sales()->create();
        \App\Models\AuditLog::query()->delete();

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['role' => UserRole::Approver->value])
            ->call('save');

        $log = \App\Models\AuditLog::where('auditable_type', User::class)->latest('id')->firstOrFail();

        $this->assertSame('updated', $log->event);
        $this->assertEquals($this->admin->id, $log->user_id);
        $this->assertArrayHasKey('role', $log->new_values);
    }
}
