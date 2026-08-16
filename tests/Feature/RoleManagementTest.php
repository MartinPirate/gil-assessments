<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Editing roles and the permissions they carry.
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        AccessControl::sync();

        $this->admin = User::factory()->role(UserRole::Admin)->create();
    }

    public function test_only_administrators_reach_it(): void
    {
        foreach ([UserRole::Sales, UserRole::Manager, UserRole::GateOfficer, UserRole::Driver] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->assertFalse(RoleResource::canAccess(), "{$role->value} must not edit roles.");
        }

        $this->actingAs($this->admin);
        $this->assertTrue(RoleResource::canAccess());
    }

    public function test_it_lists_every_role(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListRoles::class)
            ->assertCanSeeTableRecords(Role::all());
    }

    public function test_an_administrator_can_create_a_role_with_permissions(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateRole::class)
            ->fillForm([
                'name' => 'auditor',
                'display_name' => 'Auditor',
                'description' => 'Reads the trail, changes nothing.',
                'permissions' => $this->permissionIds([Permission::ViewAuditLog, Permission::ViewPayments]),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'auditor')->firstOrFail();

        $this->assertSame('Auditor', $role->display_name);
        $this->assertEqualsCanonicalizing(
            [Permission::ViewAuditLog->value, Permission::ViewPayments->value],
            $role->permissions->pluck('name')->all(),
        );
    }

    /**
     * Granting through this screen has to actually change what somebody may
     * do, otherwise it is a form over a table nobody reads.
     */
    public function test_granting_a_permission_changes_what_the_holder_may_do(): void
    {
        $sales = Role::where('name', UserRole::Sales->value)->firstOrFail();
        $person = User::factory()->role(UserRole::Sales)->create();

        $this->assertFalse($person->canApprove());

        Livewire::actingAs($this->admin)
            ->test(EditRole::class, ['record' => $sales->getKey()])
            ->fillForm(['permissions' => $this->permissionIds([
                Permission::SellDocuments,
                Permission::ViewPayments,
                Permission::ApproveDocuments,
            ])])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($person->fresh()->canApprove());
    }

    /**
     * The application checks these names in code. Renaming or deleting one
     * would take a capability away from everyone holding it, silently.
     */
    public function test_a_built_in_role_cannot_be_renamed_or_deleted(): void
    {
        $admin = Role::where('name', UserRole::Admin->value)->firstOrFail();

        $this->assertTrue(RoleResource::isBuiltIn($admin));
        $this->assertFalse(RoleResource::canDelete($admin));

        Livewire::actingAs($this->admin)
            ->test(EditRole::class, ['record' => $admin->getKey()])
            ->assertFormFieldDisabled('name');
    }

    public function test_a_role_you_created_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'temporary', 'display_name' => 'Temporary']);

        $this->assertFalse(RoleResource::isBuiltIn($role));
        $this->assertTrue(RoleResource::canDelete($role));
    }

    /**
     * @param  array<int, Permission>  $permissions
     * @return array<int, int>
     */
    protected function permissionIds(array $permissions): array
    {
        return \App\Models\Permission::query()
            ->whereIn('name', array_map(fn (Permission $p) => $p->value, $permissions))
            ->pluck('id')
            ->all();
    }
}
