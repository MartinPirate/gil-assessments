<?php

namespace App\Enums;

/**
 * The jobs this system separates.
 *
 * A role is now a row in Laratrust's `roles` table holding a set of
 * permissions; this enum is the catalogue those rows are provisioned from, so
 * the matrix lives in one reviewable place rather than being typed into a
 * screen and forgotten.
 *
 * Approving is deliberately *not* a role. It is a permission, held by
 * administrators and managers, because "may approve a document" is a thing a
 * person is trusted with rather than a job they do all day.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Sales = 'sales';
    case GateOfficer = 'gate';
    case Driver = 'driver';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::Sales => 'Sales',
            self::GateOfficer => 'Gate Officer',
            self::Driver => 'Driver',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Everything, including master data and the audit trail.',
            self::Manager => 'Approves documents over the threshold and watches the money.',
            self::Sales => 'Raises A/R invoices and follows them to payment.',
            self::GateOfficer => 'Admits and releases vehicles, and reads the gate log.',
            self::Driver => 'Sees only their own trips, routes and gate log entries.',
        };
    }

    /**
     * What this role is allowed to do.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // The administrator holds everything except Drive, which is not a
            // privilege but a statement about whose trips you own.
            self::Admin => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission) => $permission !== Permission::Drive,
            )),

            self::Manager => [
                Permission::ApproveDocuments,
                Permission::ViewPayments,
            ],

            self::Sales => [
                Permission::SellDocuments,
                Permission::ViewPayments,
            ],

            // The gate, and nothing else. Planning routes and trips is
            // office work; the officer at the barrier admits and releases
            // vehicles and reads the log of what came through.
            self::GateOfficer => [
                Permission::OperateGate,
            ],

            self::Driver => [
                Permission::Drive,
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
