<?php

namespace App\Filament\Resources\Trips\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TripsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable(),
                TextColumn::make('route.name')
                    ->searchable(),
                TextColumn::make('vehicle.id')
                    ->searchable(),
                TextColumn::make('driver.name')
                    ->searchable(),
                TextColumn::make('route_name')
                    ->searchable(),
                TextColumn::make('vehicle_number')
                    ->searchable(),
                TextColumn::make('driver_name')
                    ->searchable(),
                TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('departed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('arrived_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('cargo_description')
                    ->searchable(),
                TextColumn::make('notes')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
