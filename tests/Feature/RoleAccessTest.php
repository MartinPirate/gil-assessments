<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Landing;
use App\Filament\Pages\ArInvoice;
use App\Filament\Pages\MyTrips;
use App\Filament\Pages\VehicleGateIn;
use App\Filament\Pages\VehicleGateOut;
use App\Filament\Resources\Approvals\ApprovalRequestResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\GateLogs\GateLogResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Routes\RouteResource;
use App\Filament\Resources\Trips\TripResource;
use App\Models\Driver;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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
            // Reads the register — you cannot approve a document you are not
            // allowed to open — but does not raise one.
            'approver' => [
                UserRole::Manager,
                [ApprovalRequestResource::class, InvoiceResource::class],
                [ArInvoice::class, VehicleGateIn::class, CustomerResource::class],
            ],
            // The barrier, and nothing else. Planning routes and trips is
            // office work that used to sit in this officer's sidebar.
            'gate officer' => [
                UserRole::GateOfficer,
                [VehicleGateIn::class, VehicleGateOut::class, GateLogResource::class],
                [ArInvoice::class, ApprovalRequestResource::class, CustomerResource::class,
                    RouteResource::class, TripResource::class],
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
    #[DataProvider('roleMatrix')]
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
        $panel = Filament::getPanel('admin');

        $this->assertFalse($user->canAccessPanel($panel));
        $this->assertTrue(User::factory()->create()->canAccessPanel($panel));
    }

    public function test_approval_limits_are_enforced_per_user(): void
    {
        $junior = User::factory()->manager(10000)->create();
        $senior = User::factory()->manager()->create();      // unlimited
        $sales = User::factory()->sales()->create();

        $this->assertTrue($junior->canApproveAmount(9999));
        $this->assertFalse($junior->canApproveAmount(10001));
        $this->assertTrue($senior->canApproveAmount(1_000_000));
        $this->assertFalse($sales->canApproveAmount(1));
    }

    /**
     * The dashboard reports on money and documents.
     *
     * A gate officer has neither, and the page resolved to a filter bar over
     * three zeros — which reads as broken rather than as "not your
     * department". They start at the barrier; a driver starts at their trips.
     */
    public function test_only_people_with_figures_to_read_get_a_dashboard(): void
    {
        foreach ([UserRole::Admin, UserRole::Manager, UserRole::Sales] as $role) {
            $this->assertTrue(
                Landing::hasDashboard(User::factory()->role($role)->create()),
                "{$role->value} should have a dashboard",
            );
        }

        $gate = User::factory()->role(UserRole::GateOfficer)->create();

        $this->assertFalse(Landing::hasDashboard($gate));
        $this->assertSame(VehicleGateIn::getUrl(), Landing::urlFor($gate));

        $driverUser = User::factory()->role(UserRole::Driver)->create();
        Driver::factory()->create(['user_id' => $driverUser->getKey()]);

        $this->assertFalse(Landing::hasDashboard($driverUser->fresh()));
        $this->assertSame(MyTrips::getUrl(), Landing::urlFor($driverUser->fresh()));
    }

    /**
     * The changelog is a maintenance record. Hiding the sidebar item is not
     * enough — a URL somebody has seen once is a URL they can type.
     */
    public function test_the_changelog_is_closed_to_everybody_but_administrators(): void
    {
        $this->actingAs(User::factory()->role(UserRole::GateOfficer)->create())
            ->get('/admin/changelog')
            ->assertForbidden();

        $this->actingAs(User::factory()->role(UserRole::Sales)->create())
            ->get('/admin/changelog')
            ->assertForbidden();

        $this->actingAs(User::factory()->role(UserRole::Admin)->create())
            ->get('/admin/changelog')
            ->assertSuccessful();
    }
}
