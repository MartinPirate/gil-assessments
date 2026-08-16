<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*
                 * Assigned, not typed. Read-only on edit too: invoices
                 * snapshot customer_code at posting, so a code changed later
                 * would leave the register pointing at a partner nobody can
                 * look up.
                 */
                TextInput::make('code')
                    ->label('Code')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Assigned on save')
                    ->helperText(fn (?Customer $record) => $record
                        ? 'Codes are permanent — documents already raised refer to this one.'
                        : 'The next code in sequence is assigned when you save.'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('currency')
                    ->required()
                    ->default('KES'),
                TextInput::make('kra_pin'),
                Toggle::make('is_active')
                    ->required(),

                Section::make('Location')
                    ->description('Where deliveries for this customer go.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('address_line')
                            ->label('Address')
                            ->maxLength(200)
                            ->columnSpanFull(),
                        TextInput::make('city')
                            ->label('Town / City')
                            ->maxLength(100),
                        TextInput::make('county')
                            ->maxLength(100),
                        TextInput::make('postal_code')
                            ->label('Postal code')
                            ->maxLength(20),

                        /*
                         * Bounded, not just numeric: a latitude of 500 is not a
                         * place, and a swapped pair silently puts a Nairobi
                         * customer in the Indian Ocean.
                         */
                        TextInput::make('latitude')
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->step('0.0000001')
                            ->helperText('Decimal degrees, e.g. -1.2921'),

                        TextInput::make('longitude')
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->step('0.0000001')
                            ->helperText('Decimal degrees, e.g. 36.8219'),
                    ]),

                Repeater::make('contactPeople')
                    ->relationship()
                    ->label('Contact People')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(150),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(32),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(4)
                    ->addActionLabel('Add contact person')
                    /*
                     * No row until somebody asks for one. The repeater opened
                     * with an empty item whose name is required, so a customer
                     * could not be saved at all without inventing a contact —
                     * and contacts are optional; the first one added claims the
                     * default by itself.
                     */
                    ->defaultItems(0)
                    ->columnSpanFull(),

                // Options come from what is already saved, so on a brand new
                // customer this is empty — the first contact claims the role
                // by itself when it is created, and the field is only worth
                // touching once there is more than one person to choose from.
                Select::make('contact_person_id')
                    ->label('Default Contact')
                    ->options(fn (?Customer $record) => $record
                        ?->contactPeople()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all() ?? [])
                    ->helperText('The contact an invoice raised against this customer starts with. Save the customer first to choose one.')
                    ->native(false),
            ]);
    }
}
