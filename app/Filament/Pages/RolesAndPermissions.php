<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * What each role may do, and how many people hold it.
 *
 * Deliberately read-only. Roles here are an enum with explicit capability
 * methods rather than rows in a permissions table, which is what makes them
 * safe: a capability cannot be granted by accident, and every check is
 * greppable. An editor on this screen would have to write to something, and
 * the only honest thing to write to is the code.
 *
 * So this answers the question people actually open a permissions screen to
 * ask — "who can approve an invoice, and who currently is one" — and points
 * at the file for changing it.
 */
class RolesAndPermissions extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedKey;

    protected static string | UnitEnum | null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Roles and permissions';

    protected string $view = 'filament.pages.roles-and-permissions';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->role()->canAdminister();
    }

    /**
     * The capability matrix, built from the enum itself so this screen cannot
     * drift from what the application actually enforces.
     *
     * @return array<int, array{label: string, capabilities: array<string, bool>, holders: int}>
     */
    public function getMatrix(): array
    {
        $counts = User::query()
            ->selectRaw('[role], COUNT(*) AS total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return collect(UserRole::cases())
            ->map(fn (UserRole $role): array => [
                'label' => $role->label(),
                'value' => $role->value,
                'holders' => (int) ($counts[$role->value] ?? 0),
                'capabilities' => [
                    'Raise and view invoices' => $role->canSell(),
                    'Decide approvals' => $role->canApprove(),
                    'Record vehicle movements' => $role->canOperateGate(),
                    'Plan and assign trips' => $role->canManageTrips(),
                    'See payments' => $role->canViewPayments(),
                    'Read the audit trail' => $role->canViewAuditLog(),
                    'Edit master data' => $role->canAdminister(),
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getCapabilityLabels(): array
    {
        return array_keys($this->getMatrix()[0]['capabilities'] ?? []);
    }
}
