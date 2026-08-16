<?php

namespace App\Filament\Resources\Drivers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('national_id')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Login')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                IconColumn::make('has_licence')
                    ->label('Licence')
                    ->boolean()
                    ->state(fn ($record) => $record->hasLicence())
                    ->trueIcon('heroicon-o-identification')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->falseColor('warning'),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()->button()->outlined()->size('xs'),
                EditAction::make()->button()->outlined()->size('xs'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
