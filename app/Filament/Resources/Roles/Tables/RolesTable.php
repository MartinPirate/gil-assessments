<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Role')
                    ->weight('bold')
                    ->description(fn (Role $record) => $record->description)
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Key')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->alignRight(),

                /*
                 * Counted through the pivot. Laratrust resolves $role->users
                 * dynamically from its user_models config rather than as a
                 * declared relation, so withCount() has nothing to hang on.
                 */
                TextColumn::make('accounts')
                    ->label('Accounts')
                    ->alignRight()
                    ->state(fn (Role $record) => DB::table('role_user')
                        ->where('role_id', $record->getKey())
                        ->where('user_type', User::class)
                        ->count()),

                IconColumn::make('built_in')
                    ->label('Built in')
                    ->boolean()
                    ->state(fn (Role $record) => RoleResource::isBuiltIn($record))
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil')
                    ->trueColor('gray')
                    ->falseColor('success'),
            ])
            ->recordActions([
                EditAction::make()->button()->outlined()->size('xs'),
                DeleteAction::make()
                    ->button()
                    ->outlined()
                    ->size('xs')
                    ->visible(fn (Role $record) => ! RoleResource::isBuiltIn($record)),
            ]);
    }
}
