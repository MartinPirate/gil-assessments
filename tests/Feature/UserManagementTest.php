<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\User;
use Filament\Facades\Filament;
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
        foreach ([UserRole::Sales, UserRole::Manager, UserRole::GateOfficer, UserRole::Driver] as $role) {
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

        $this->assertSame(UserRole::Sales, $user->role());
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
            ->fillForm(['role' => UserRole::Manager->value, 'approval_limit' => 25000])
            ->call('save')
            ->assertHasNoFormErrors();

        $other->refresh();

        $this->assertSame(UserRole::Manager, $other->role());
        $this->assertEquals(25000, $other->approval_limit);
    }

    /* -----------------------------------------------------------------
     | Driver linking
     |
     | Every driver has a login, so the pairing is made once when the driver
     | is created and cannot be moved or released from the user side — that
     | would leave a driver record pointing at nothing.
     | ----------------------------------------------------------------- */

    public function test_the_user_form_does_not_offer_to_link_a_driver(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateUser::class)
            ->fillForm(['role' => UserRole::Driver->value])
            ->assertFormFieldDoesNotExist('driver_id');
    }

    /**
     * Demoting a driver's account no longer detaches the driver record. Access
     * to the portal is decided by the role, so the pairing can safely survive.
     */
    public function test_changing_a_driver_to_another_role_keeps_the_link(): void
    {
        $driver = Driver::factory()->create(['name' => 'Ex Driver', 'national_id' => '903', 'phone' => '0700000903']);
        $user = $driver->user;

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['role' => UserRole::GateOfficer->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals($user->getKey(), $driver->fresh()->user_id);
        $this->assertSame(UserRole::GateOfficer, $user->fresh()->role());
    }

    /* -----------------------------------------------------------------
     | Sign-in
     | ----------------------------------------------------------------- */

    public function test_a_deactivated_user_cannot_reach_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertFalse(User::factory()->inactive()->create()->canAccessPanel($panel));
        $this->assertTrue(User::factory()->create()->canAccessPanel($panel));
    }

    /**
     * User changes are consequential, so they belong in the audit trail.
     */
    public function test_user_changes_are_audited(): void
    {
        $user = User::factory()->sales()->create();
        AuditLog::query()->delete();

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm(['role' => UserRole::Manager->value])
            ->call('save');

        $log = AuditLog::where('auditable_type', User::class)->latest('id')->firstOrFail();

        $this->assertSame('updated', $log->event);
        $this->assertEquals($this->admin->id, $log->user_id);
        $this->assertArrayHasKey('role', $log->new_values);
    }
}
