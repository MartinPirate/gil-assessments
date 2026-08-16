<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Models\Vehicle;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
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

                SpatieMediaLibraryFileUpload::make('photos')
                    ->label('Photographs')
                    ->collection(Vehicle::PHOTOS)
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->maxFiles(6)
                    ->maxSize(5 * 1024)
                    ->imageEditor()
                    ->conversion('thumb')
                    ->columnSpanFull()
                    // The first is what the fleet list and the record page
                    // lead with, so the order is worth being able to change.
                    ->helperText('JPEG, PNG or WebP, up to 5 MB each. The first photograph is the one shown everywhere else.'),
            ]);
    }
}
