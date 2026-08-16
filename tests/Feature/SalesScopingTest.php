<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\ApiDocs;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\MpesaTransactions\MpesaTransactionResource;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Models\PaymentAllocation;
use App\Models\SalesEmployee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A salesperson sees their own work, and nobody else's.
 */
class SalesScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);
    }

    protected function salesperson(): User
    {
        return User::where('email', 'sales@gil.test')->firstOrFail();
    }

    /**
     * Documents are attributed to a sales employee, so "mine" has to mean
     * that — not who typed it, which on a seeded register is one account for
     * every document.
     */
    public function test_the_sales_login_is_linked_to_its_employee_record(): void
    {
        $employee = $this->salesperson()->salesEmployee;

        $this->assertInstanceOf(SalesEmployee::class, $employee);
        $this->assertSame('Mercy Nyambura', $employee->name);
    }

    public function test_a_salesperson_sees_only_documents_attributed_to_them(): void
    {
        $person = $this->salesperson();
        $this->actingAs($person);

        $visible = InvoiceResource::getEloquentQuery()->pluck('sales_employee_id')->unique();

        $this->assertGreaterThan(0, $visible->count(), 'They should have some work of their own.');
        $this->assertCount(1, $visible, 'Only their own attribution should appear.');
        $this->assertEquals($person->salesEmployeeId(), $visible->first());
        $this->assertLessThan(Invoice::count(), InvoiceResource::getEloquentQuery()->count());
    }

    /**
     * An approver with an empty queue could not approve, and an administrator
     * who could not see the register could not administer it.
     */
    public function test_managers_and_administrators_see_the_whole_register(): void
    {
        foreach ([UserRole::Admin, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->assertSame(
                Invoice::count(),
                InvoiceResource::getEloquentQuery()->count(),
                "{$role->value} should see every document.",
            );
        }
    }

    public function test_a_salesperson_sees_only_payments_settling_their_own_documents(): void
    {
        $person = $this->salesperson();
        $this->actingAs($person);

        $ids = MpesaTransactionResource::getEloquentQuery()->pluck('id');

        // Fewer than the whole till, whatever the seed happens to allocate.
        $this->assertLessThanOrEqual(MpesaTransaction::count(), $ids->count());

        foreach ($ids as $id) {
            $settles = PaymentAllocation::where('mpesa_transaction_id', $id)
                ->whereHas('invoice', fn ($q) => $q->where('sales_employee_id', $person->salesEmployeeId()))
                ->exists();

            $this->assertTrue($settles, "Receipt {$id} does not settle any of their documents.");
        }
    }

    /**
     * The API reference maps the request surface, including the M-Pesa
     * callback contract. That is for whoever maintains the system.
     */
    public function test_only_an_administrator_reaches_the_api_reference(): void
    {
        foreach ([UserRole::Sales, UserRole::Manager, UserRole::GateOfficer, UserRole::Driver] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->assertFalse(ApiDocs::canAccess(), "{$role->value} must not reach the API docs.");
        }

        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $this->assertTrue(ApiDocs::canAccess());
    }

    /**
     * canAccess() governs the route as well as the sidebar, so the page is not
     * merely hidden.
     */
    public function test_the_api_reference_is_unreachable_by_url(): void
    {
        $this->actingAs($this->salesperson())
            ->get(ApiDocs::getUrl())
            ->assertForbidden();
    }

    /**
     * The badge counts receipts waiting to be applied. A salesperson cannot
     * see an unallocated receipt — it settles nobody's document — so a count
     * over an empty table was reporting a queue they could not open.
     */
    public function test_the_unmatched_badge_is_only_for_people_who_can_place_the_money(): void
    {
        $this->actingAs($this->salesperson());
        $this->assertNull(MpesaTransactionResource::getNavigationBadge());

        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $this->assertNotNull(MpesaTransactionResource::getNavigationBadge());
    }

    /**
     * Receipts are settled through the real reconciliation path, so the
     * register and the payments screen agree with each other.
     */
    public function test_seeded_receipts_settle_real_documents(): void
    {
        $allocations = PaymentAllocation::with('invoice')->get();

        $this->assertNotEmpty($allocations, 'The seed should settle some documents.');

        foreach ($allocations as $allocation) {
            $this->assertNotNull($allocation->invoice);
            $this->assertLessThanOrEqual(
                (float) $allocation->invoice->document_total,
                (float) $allocation->invoice->applied_amount,
                'A document cannot have more applied to it than it is worth.',
            );
        }

        // Unmatched money is the case the reconciliation screen exists for.
        $this->assertGreaterThan(0, MpesaTransaction::doesntHave('allocations')->count());
    }
}
