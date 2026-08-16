<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * What each role may do, and how many people hold it.
 *
 * Roles and permissions are Laratrust rows now, but the matrix they are
 * provisioned from is UserRole::permissions() — so this screen reads the
 * enum, which is what AccessControl::sync() writes to the database. Still
 * read-only: granting a capability is a reviewed change to that map, not a
 * checkbox somebody ticks on a Friday.
 *
 * It answers the question people actually open a permissions screen to ask —
 * "who can approve an invoice, and who currently can" — and points at the file
 * for changing it.
 */
class RolesAndPermissions extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Roles and permissions';

    protected string $view = 'filament.pages.roles-and-permissions';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAdminister();
    }

    /**
     * The capability matrix, built from the enum itself so this screen cannot
     * drift from what the application actually enforces.
     *
     * @return array<int, array{label: string, capabilities: array<string, bool>, holders: int}>
     */
    public function getMatrix(): array
    {
        // Counted through the pivot, which is where a role now lives.
        $counts = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_type', User::class)
            ->selectRaw('[roles].[name] AS role_name, COUNT(*) AS total')
            ->groupBy('roles.name')
            ->pluck('total', 'role_name');

        return collect(UserRole::cases())
            ->map(function (UserRole $role) use ($counts): array {
                $held = collect($role->permissions());

                return [
                    'label' => $role->label(),
                    'value' => $role->value,
                    'description' => $role->description(),
                    'holders' => (int) ($counts[$role->value] ?? 0),
                    'capabilities' => collect(Permission::cases())
                        ->mapWithKeys(fn (Permission $permission) => [
                            $permission->label() => $held->contains($permission),
                        ])
                        ->all(),
                ];
            })
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
