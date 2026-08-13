<?php

namespace App\Enums;

/**
 * The four jobs this system separates.
 *
 * Kept as an enum with explicit capability checks rather than a permissions
 * table: the set is small and fixed, and a generic ACL would be machinery with
 * nothing to configure.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Sales = 'sales';
    case Approver = 'approver';
    case GateOfficer = 'gate';
    case Driver = 'driver';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Sales => 'Sales',
            self::Approver => 'Approver',
            self::GateOfficer => 'Gate Officer',
            self::Driver => 'Driver',
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

    /** May create and view A/R invoices. */
    public function canSell(): bool
    {
        return in_array($this, [self::Admin, self::Sales], true);
    }

    /** May decide on invoices that breached the approval threshold. */
    public function canApprove(): bool
    {
        return in_array($this, [self::Admin, self::Approver], true);
    }

    /** May record vehicle movements. */
    public function canOperateGate(): bool
    {
        return in_array($this, [self::Admin, self::GateOfficer], true);
    }

    /** May edit master data and allocate payments by hand. */
    public function canAdminister(): bool
    {
        return $this === self::Admin;
    }

    /** May see payment records. */
    public function canViewPayments(): bool
    {
        return in_array($this, [self::Admin, self::Sales, self::Approver], true);
    }

    /**
     * A driver signs in only to see their own work. Deliberately narrow: this
     * role must not reach invoices, payments or other drivers' trips.
     */
    public function isDriver(): bool
    {
        return $this === self::Driver;
    }

    /** May plan and assign trips. */
    public function canManageTrips(): bool
    {
        return in_array($this, [self::Admin, self::GateOfficer], true);
    }

    /** May read the audit trail. */
    public function canViewAuditLog(): bool
    {
        return $this === self::Admin;
    }
}
