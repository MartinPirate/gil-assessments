<?php

namespace App\Filament\Resources\Routes\Tables;

use App\Filament\Resources\Routes\RouteResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('origin')
                    ->searchable(),
                TextColumn::make('destination')
                    ->searchable(),
                TextColumn::make('distance_km')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('estimated_hours')
                    ->numeric()
                    ->sortable(),
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
                EditAction::make()
                    ->visible(fn (): bool => RouteResource::canPlan()),
            ])
            ->toolbarActions([
                // Hidden outright for a driver, which also takes the row
                // checkboxes with it — a select-all on records you may not
                // touch is an invitation to a 403.
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])
                    ->visible(fn (): bool => RouteResource::canPlan()),
            ]);
    }
}
