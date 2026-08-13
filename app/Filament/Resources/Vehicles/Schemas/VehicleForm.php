<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('vehicle_number')
                    ->required(),
                TextInput::make('make'),
                TextInput::make('vehicle_type'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
