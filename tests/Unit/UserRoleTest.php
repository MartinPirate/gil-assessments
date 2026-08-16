<?php

namespace Tests\Unit;

use App\Enums\Permission;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The permission matrix, pinned so a future change cannot silently widen
 * anyone's access.
 *
 * This is the map AccessControl::sync() writes into Laratrust, so asserting it
 * here is asserting what the database will hold.
 */
class UserRoleTest extends TestCase
{
    /**
     * @return array<string, array{UserRole, array<int, Permission>}>
     */
    public static function matrix(): array
    {
        return [
            'admin' => [UserRole::Admin, [
                Permission::SellDocuments,
                Permission::ApproveDocuments,
                Permission::ViewPayments,
                Permission::OperateGate,
                Permission::ManageTrips,
                Permission::ViewAuditLog,
                Permission::AdministerSystem,
            ]],
            'manager' => [UserRole::Manager, [
                Permission::ApproveDocuments,
                Permission::ViewPayments,
            ]],
            'sales' => [UserRole::Sales, [
                Permission::SellDocuments,
                Permission::ViewPayments,
            ]],
            // The barrier only. Planning routes and trips is office work.
            'gate officer' => [UserRole::GateOfficer, [
                Permission::OperateGate,
            ]],
            'driver' => [UserRole::Driver, [
                Permission::Drive,
            ]],
        ];
    }

    /**
     * @param  array<int, Permission>  $granted
     */
    #[DataProvider('matrix')]
    public function test_each_role_holds_exactly_its_own_permissions(UserRole $role, array $granted): void
    {
        $held = $role->permissions();

        foreach (Permission::cases() as $permission) {
            $expected = in_array($permission, $granted, true);

            $this->assertSame(
                $expected,
                in_array($permission, $held, true),
                "{$role->value} should ".($expected ? 'hold' : 'NOT hold')." {$permission->value}",
            );
        }
    }

    /**
     * Approving is a trust, not a job. It must never be the whole of what a
     * role is, and it must reach more than one role.
     */
    public function test_approving_is_a_permission_held_by_more_than_one_role(): void
    {
        $holders = array_filter(
            UserRole::cases(),
            fn (UserRole $role) => in_array(Permission::ApproveDocuments, $role->permissions(), true),
        );

        $this->assertGreaterThan(1, count($holders));
        $this->assertContains(UserRole::Admin, $holders);
        $this->assertContains(UserRole::Manager, $holders);
    }

    public function test_every_role_has_a_human_label_and_description(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotSame('', $role->label());
            $this->assertNotSame($role->value, $role->label());
            $this->assertNotSame('', $role->description());
        }
    }

    public function test_options_covers_every_case(): void
    {
        $this->assertCount(count(UserRole::cases()), UserRole::options());
        $this->assertArrayHasKey('driver', UserRole::options());
    }

    /**
     * Only administrators may read who did what.
     */
    public function test_the_audit_trail_is_admin_only(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertSame(
                $role === UserRole::Admin,
                in_array(Permission::ViewAuditLog, $role->permissions(), true),
            );
        }
    }

    /**
     * A driver's reach is deliberately narrow.
     */
    public function test_a_driver_holds_nothing_but_driving(): void
    {
        $this->assertSame([Permission::Drive], UserRole::Driver->permissions());
    }
}
