<?php

namespace App\Filament\Resources\Trips\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reference')
                    ->required(),
                Select::make('route_id')
                    ->relationship('route', 'name')
                    ->required(),
                Select::make('vehicle_id')
                    ->relationship('vehicle', 'id')
                    ->required(),
                Select::make('driver_id')
                    ->relationship('driver', 'name')
                    ->required(),
                TextInput::make('route_name')
                    ->required(),
                TextInput::make('vehicle_number')
                    ->required(),
                TextInput::make('driver_name')
                    ->required(),
                DateTimePicker::make('scheduled_at')
                    ->required(),
                DateTimePicker::make('departed_at'),
                DateTimePicker::make('arrived_at'),
                TextInput::make('status')
                    ->required()
                    ->default('Scheduled'),
                TextInput::make('cargo_description'),
                TextInput::make('notes'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
