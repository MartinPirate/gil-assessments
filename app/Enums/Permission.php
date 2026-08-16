<?php

namespace App\Enums;

/**
 * Everything a user may be allowed to do, named once.
 *
 * These are the rows Laratrust stores in `permissions`, and the strings every
 * check in the application asks about. Roles are collections of these — see
 * UserRole::permissions() — so granting a capability to another role is a
 * change in one place rather than a new condition scattered through the panel.
 */
enum Permission: string
{
    case SellDocuments = 'sell-documents';
    case ApproveDocuments = 'approve-documents';
    case ViewPayments = 'view-payments';
    case OperateGate = 'operate-gate';
    case ManageTrips = 'manage-trips';
    case Drive = 'drive';
    case ViewAuditLog = 'view-audit-log';
    case AdministerSystem = 'administer-system';

    public function label(): string
    {
        return match ($this) {
            self::SellDocuments => 'Raise and view sales documents',
            self::ApproveDocuments => 'Approve documents over the threshold',
            self::ViewPayments => 'View payments and reconciliation',
            self::OperateGate => 'Record vehicle movements',
            self::ManageTrips => 'Plan and assign trips',
            self::Drive => 'See own trips in the driver portal',
            self::ViewAuditLog => 'Read the audit trail',
            self::AdministerSystem => 'Edit master data and administer the system',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $permission) => [$permission->value => $permission->label()])
            ->all();
    }
}
