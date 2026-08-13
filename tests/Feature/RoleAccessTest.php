<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\ArInvoice;
use App\Filament\Pages\VehicleGateIn;
use App\Filament\Pages\VehicleGateOut;
use App\Filament\Resources\Approvals\ApprovalRequestResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\GateLogs\GateLogResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who can reach what.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{UserRole, array<int, class-string>, array<int, class-string>}>
     */
    public static function roleMatrix(): array
    {
        return [
            'sales' => [
                UserRole::Sales,
                [ArInvoice::class, InvoiceResource::class],
                [ApprovalRequestResource::class, VehicleGateIn::class, CustomerResource::class],
            ],
            'approver' => [
                UserRole::Approver,
                [ApprovalRequestResource::class],
                [ArInvoice::class, VehicleGateIn::class, CustomerResource::class],
            ],
            'gate officer' => [
                UserRole::GateOfficer,
                [VehicleGateIn::class, VehicleGateOut::class, GateLogResource::class],
                [ArInvoice::class, ApprovalRequestResource::class, CustomerResource::class],
            ],
            'admin' => [
                UserRole::Admin,
                [ArInvoice::class, ApprovalRequestResource::class, VehicleGateIn::class, CustomerResource::class],
                [],
            ],
        ];
    }

    /**
     * @param  array<int, class-string>  $allowed
     * @param  array<int, class-string>  $denied
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('roleMatrix')]
    public function test_each_role_reaches_only_its_own_screens(UserRole $role, array $allowed, array $denied): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        foreach ($allowed as $screen) {
            $this->assertTrue($screen::canAccess(), "{$role->value} should reach {$screen}");
        }

        foreach ($denied as $screen) {
            $this->assertFalse($screen::canAccess(), "{$role->value} should NOT reach {$screen}");
        }
    }

    /**
     * A deactivated account keeps its history but must not be able to sign in.
     */
    public function test_a_deactivated_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->inactive()->create();
        $panel = \Filament\Facades\Filament::getPanel('admin');

        $this->assertFalse($user->canAccessPanel($panel));
        $this->assertTrue(User::factory()->create()->canAccessPanel($panel));
    }

    public function test_approval_limits_are_enforced_per_user(): void
    {
        $junior = User::factory()->approver(10000)->create();
        $senior = User::factory()->approver()->create();      // unlimited
        $sales = User::factory()->sales()->create();

        $this->assertTrue($junior->canApproveAmount(9999));
        $this->assertFalse($junior->canApproveAmount(10001));
        $this->assertTrue($senior->canApproveAmount(1_000_000));
        $this->assertFalse($sales->canApproveAmount(1));
    }
}
