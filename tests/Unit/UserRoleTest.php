<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

/**
 * The capability matrix, pinned so a future role change cannot silently widen
 * anyone's access.
 */
class UserRoleTest extends TestCase
{
    /**
     * @return array<string, array{UserRole, array<int, string>}>
     */
    public static function capabilities(): array
    {
        return [
            'admin' => [UserRole::Admin, ['canSell', 'canApprove', 'canOperateGate', 'canAdminister', 'canViewPayments', 'canManageTrips', 'canViewAuditLog']],
            'sales' => [UserRole::Sales, ['canSell', 'canViewPayments']],
            'approver' => [UserRole::Approver, ['canApprove', 'canViewPayments']],
            'gate officer' => [UserRole::GateOfficer, ['canOperateGate', 'canManageTrips']],
            'driver' => [UserRole::Driver, ['isDriver']],
        ];
    }

    /**
     * @param  array<int, string>  $granted
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('capabilities')]
    public function test_each_role_has_exactly_its_own_capabilities(UserRole $role, array $granted): void
    {
        $all = ['canSell', 'canApprove', 'canOperateGate', 'canAdminister', 'canViewPayments', 'canManageTrips', 'canViewAuditLog', 'isDriver'];

        foreach ($all as $capability) {
            $expected = in_array($capability, $granted, true);

            $this->assertSame(
                $expected,
                $role->{$capability}(),
                "{$role->value} should ".($expected ? 'have' : 'NOT have')." {$capability}",
            );
        }
    }

    public function test_every_role_has_a_human_label(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotSame('', $role->label());
            $this->assertNotSame($role->value, $role->label());
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
            $this->assertSame($role === UserRole::Admin, $role->canViewAuditLog());
        }
    }
}
