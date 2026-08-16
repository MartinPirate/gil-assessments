<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Role')->columns(2)->schema([
                TextInput::make('name')
                    ->label('Key')
                    ->required()
                    ->maxLength(60)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    // The application checks these names in code; renaming one
                    // would revoke a capability from everyone holding it.
                    ->disabled(fn (?Role $record) => RoleResource::isBuiltIn($record))
                    ->helperText(fn (?Role $record) => RoleResource::isBuiltIn($record)
                        ? 'Built in — the application refers to this role by name.'
                        : 'Lowercase, no spaces. Used in code and never shown to users.'),

                TextInput::make('display_name')
                    ->label('Name')
                    ->required()
                    ->maxLength(100),

                TextInput::make('description')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]),

            Section::make('Permissions')
                ->description('What somebody holding this role may do.')
                ->schema([
                    /*
                     * Bound by display_name, which AccessControl::sync() fills
                     * from Permission::label(). Keying the options by the
                     * permission's name instead would post strings into a
                     * pivot column expecting ids.
                     */
                    CheckboxList::make('permissions')
                        ->hiddenLabel()
                        ->relationship('permissions', 'display_name')
                        ->descriptions(fn () => Permission::query()
                            ->pluck('name', 'id')
                            ->all())
                        ->columns(2)
                        ->bulkToggleable()
                        ->searchable()
                        ->helperText('Changes take effect the next time the person loads a page.'),
                ]),
        ]);
    }
}
