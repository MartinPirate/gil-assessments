<?php

namespace App\Filament\Resources\Drivers\Schemas;

use App\Models\Driver;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Driver')->columns(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(150),
                TextInput::make('national_id')
                    ->label('Driver ID')
                    ->required()
                    ->maxLength(32),
                TextInput::make('phone')
                    ->tel()
                    ->required()
                    ->maxLength(32),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive drivers keep their history but cannot be assigned new trips.'),
            ]),

            Section::make('Licence')
                ->description('A photograph or scan of the licence this driver carries.')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('licence')
                        ->hiddenLabel()
                        ->collection(Driver::LICENCE)
                        ->disk('local')
                        ->visibility('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(5 * 1024)
                        ->downloadable()
                        ->openable()
                        // Replaces rather than accumulates: a driver has one
                        // current licence, and the collection is singleFile.
                        ->helperText('JPEG, PNG, WebP or PDF, up to 5 MB. Uploading again replaces the current one.'),
                ]),

            /*
             * Every driver has a login, so the account is created here with
             * the driver rather than linked afterwards from the Users screen.
             * These two fields belong to the user row; the create and edit
             * pages take them out of the payload and write them there.
             */
            Section::make('Login')->columns(2)->schema([
                TextInput::make('email')
                    ->label('Login email')
                    ->email()
                    ->required()
                    ->maxLength(191)
                    ->unique(
                        table: 'users',
                        column: 'email',
                        // The driver's own account must not collide with itself.
                        modifyRuleUsing: fn ($rule, ?Driver $record) => $rule->ignore($record?->user_id),
                    )
                    ->afterStateHydrated(fn ($component, ?Driver $record) => $component->state($record?->user?->email))
                    ->dehydrated(),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->required(fn (?Driver $record) => $record === null)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (?Driver $record) => $record
                        ? 'Leave blank to keep the current password.'
                        : 'The driver signs in with this to see their own trips.'),
            ]),
        ]);
    }
}
