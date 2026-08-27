<?php

namespace App\Filament\Pages\Resources\Roles;

use App\Enums\UserRole;
use App\Filament\Pages\Resources\Roles\Pages\CreateRole;
use App\Filament\Pages\Resources\Roles\Pages\EditRole;
use App\Filament\Pages\Resources\Roles\Pages\ListRoles;
use App\Filament\Pages\Resources\Roles\Schemas\RoleForm;
use App\Filament\Pages\Resources\Roles\Tables\RolesTable;
use App\Models\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Roles, and the permissions each one carries.
 *
 * The five shipped roles are provisioned from UserRole::permissions() and the
 * application checks their names in code, so they cannot be renamed or deleted
 * here — only their permissions edited. Roles you add yourself are yours to do
 * as you like with.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'role';

    public static function canAccess(): bool
    {
        return Auth::user()?->canAdminister() ?? false;
    }

    /**
     * A role the code knows about by name. Renaming or removing one would take
     * a capability away from everyone holding it, silently.
     */
    public static function isBuiltIn(?Role $role): bool
    {
        return $role !== null && UserRole::tryFrom((string) $role->name) !== null;
    }

    public static function canDelete(Model $record): bool
    {
        return ! static::isBuiltIn($record);
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
