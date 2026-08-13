<?php

namespace App\Filament\Resources\Items\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('item_no')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                TextInput::make('uom')
                    ->required()
                    ->default('Bales'),
                TextInput::make('warehouse')
                    ->required()
                    ->default('FG WHS'),
                TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('qty_in_warehouse')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
