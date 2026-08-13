<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('contact_person'),
                TextInput::make('currency')
                    ->required()
                    ->default('KES'),
                TextInput::make('kra_pin'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
