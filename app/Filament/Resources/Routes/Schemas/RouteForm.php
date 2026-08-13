<?php

namespace App\Filament\Resources\Routes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('origin')
                    ->required(),
                TextInput::make('destination')
                    ->required(),
                TextInput::make('distance_km')
                    ->numeric(),
                TextInput::make('estimated_hours')
                    ->numeric(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
