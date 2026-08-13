<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('email')->searchable()->copyable(),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof UserRole ? $state->label() : $state)
                    ->color(fn ($state): string => match ($state instanceof UserRole ? $state : null) {
                        UserRole::Admin => 'danger',
                        UserRole::Approver => 'warning',
                        UserRole::Sales => 'success',
                        UserRole::GateOfficer => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('approval_limit')
                    ->label('Approval limit')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('unlimited')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('driver.name')->label('Driver record')->placeholder('—')->toggleable(),

                IconColumn::make('is_active')->label('Active')->boolean(),

                TextColumn::make('created_at')->dateTime('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')->options(UserRole::options()),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                // Deleting yourself locks you out; deactivate instead.
                DeleteAction::make()->visible(fn (User $record) => ! $record->is(Auth::user())),
            ]);
    }
}
