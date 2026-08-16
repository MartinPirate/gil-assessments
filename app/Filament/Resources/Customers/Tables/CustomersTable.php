<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('contactPerson.name')
                    ->label('Contact Person')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('contactPerson.email')
                    ->label('Contact Email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('contactPerson.phone')
                    ->label('Contact Phone')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('contactPeople_count')
                    ->label('Contacts')
                    ->counts('contactPeople')
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->label('Location')
                    ->description(fn ($record) => $record->address_line)
                    ->searchable(['city', 'county', 'address_line'])
                    ->placeholder('—'),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('kra_pin')
                    ->searchable(),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
