<?php

namespace App\Filament\Resources\SalesEmployees\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SalesEmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('position'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
