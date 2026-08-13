<?php

namespace App\Filament\Resources\Drivers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('national_id')
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
