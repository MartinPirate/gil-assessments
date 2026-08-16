<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The audit trail.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_record_is_logged_with_the_actor(): void
    {
        $user = User::factory()->create(['name' => 'Grace Achieng']);
        $this->actingAs($user);

        $vehicle = Vehicle::create(['vehicle_number' => 'KDA 123A', 'make' => 'Isuzu']);

        $log = AuditLog::where('auditable_type', Vehicle::class)->firstOrFail();

        $this->assertSame(AuditLog::CREATED, $log->event);
        $this->assertEquals($vehicle->id, $log->auditable_id);
        $this->assertSame('KDA 123A', $log->auditable_label);
        $this->assertEquals($user->id, $log->user_id);
        // Snapshotted, so the log still reads correctly if the user is renamed.
        $this->assertSame('Grace Achieng', $log->user_name);
    }

    public function test_an_update_records_only_what_changed(): void
    {
        $this->actingAs(User::factory()->create());

        $customer = Customer::create(['code' => 'CC1', 'name' => 'Old Name', 'currency' => 'KES']);
        AuditLog::query()->delete();

        $customer->update(['name' => 'New Name']);

        $log = AuditLog::where('event', AuditLog::UPDATED)->firstOrFail();

        $this->assertSame(['name' => 'Old Name'], $log->old_values);
        $this->assertSame(['name' => 'New Name'], $log->new_values);
        // The whole row must not be dumped into the log.
        $this->assertArrayNotHasKey('code', $log->new_values);
    }

    public function test_a_save_that_changes_nothing_is_not_logged(): void
    {
        $this->actingAs(User::factory()->create());

        $customer = Customer::create(['code' => 'CC1', 'name' => 'Same', 'currency' => 'KES']);
        AuditLog::query()->delete();

        $customer->update(['name' => 'Same']);

        // An audit trail full of no-op entries hides the ones that matter.
        $this->assertSame(0, AuditLog::count());
    }

    public function test_deleting_is_logged(): void
    {
        $this->actingAs(User::factory()->create());

        $vehicle = Vehicle::create(['vehicle_number' => 'KDB 456B']);
        AuditLog::query()->delete();

        $vehicle->delete();

        $this->assertSame(AuditLog::DELETED, AuditLog::firstOrFail()->event);
    }

    /**
     * The single most important property of this module: it must never write a
     * credential into a table that admins can read.
     */
    public function test_passwords_and_tokens_are_never_recorded(): void
    {
        $this->actingAs(User::factory()->create());

        $user = User::factory()->create(['name' => 'Someone']);
        $user->update(['password' => 'brand-new-secret', 'name' => 'Renamed']);

        foreach (AuditLog::where('auditable_type', User::class)->get() as $log) {
            $recorded = array_merge(
                array_keys($log->old_values ?? []),
                array_keys($log->new_values ?? []),
            );

            $this->assertNotContains('password', $recorded);
            $this->assertNotContains('remember_token', $recorded);

            $encoded = json_encode([$log->old_values, $log->new_values]);
            $this->assertStringNotContainsString('brand-new-secret', $encoded);
        }
    }

    public function test_timestamps_are_not_treated_as_changes(): void
    {
        $this->actingAs(User::factory()->create());

        $vehicle = Vehicle::create(['vehicle_number' => 'KDC 789C']);
        AuditLog::query()->delete();

        $vehicle->touch();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_system_changes_are_logged_without_a_user(): void
    {
        // No authenticated user — e.g. a callback or a console command.
        Vehicle::create(['vehicle_number' => 'KDD 000D']);

        $log = AuditLog::firstOrFail();

        $this->assertNull($log->user_id);
        $this->assertNull($log->user_name);
    }

    public function test_the_diff_list_pairs_old_and_new_values(): void
    {
        $this->actingAs(User::factory()->create());

        $customer = Customer::create(['code' => 'CC9', 'name' => 'Before', 'currency' => 'KES']);
        AuditLog::query()->delete();
        $customer->update(['name' => 'After']);

        $changes = AuditLog::firstOrFail()->changes_list;

        $this->assertCount(1, $changes);
        $this->assertSame(['field' => 'name', 'from' => 'Before', 'to' => 'After'], $changes[0]);
    }

    public function test_only_admins_can_read_the_audit_trail(): void
    {
        foreach ([UserRole::Sales, UserRole::Manager, UserRole::GateOfficer, UserRole::Driver] as $role) {
            $this->actingAs(User::factory()->role($role)->create());
            $this->assertFalse(AuditLogResource::canAccess(), "{$role->value} must not read the audit log.");
        }

        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $this->assertTrue(AuditLogResource::canAccess());
    }

    /**
     * A log that can be edited is not a log.
     */
    public function test_the_audit_trail_is_immutable_through_the_panel(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());

        Vehicle::create(['vehicle_number' => 'KDE 111E']);
        $log = AuditLog::firstOrFail();

        $this->assertFalse(AuditLogResource::canCreate());
        $this->assertFalse(AuditLogResource::canEdit($log));
        $this->assertFalse(AuditLogResource::canDelete($log));
    }

    public function test_the_request_context_is_captured(): void
    {
        $this->actingAs(User::factory()->create());

        Vehicle::create(['vehicle_number' => 'KDF 222F']);

        $log = AuditLog::firstOrFail();

        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->url);
    }
}
