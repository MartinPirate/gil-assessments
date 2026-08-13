<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(150),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(191)
                    ->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    // Required when creating; left blank on edit means "leave
                    // the existing password alone".
                    ->required(fn (?User $record) => $record === null)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                    ->helperText(fn (?User $record) => $record ? 'Leave blank to keep the current password.' : null),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    // An admin who deactivates their own account would be
                    // locked out with no way back in.
                    ->disabled(fn (?User $record) => $record?->is(Auth::user()) ?? false)
                    ->helperText(fn (?User $record) => $record?->is(Auth::user())
                        ? 'You cannot deactivate your own account.'
                        : 'Inactive users keep their history but cannot sign in.'),
            ]),

            Section::make('Role & permissions')->columns(2)->schema([
                Select::make('role')
                    ->options(UserRole::options())
                    ->required()
                    ->live()
                    ->default(UserRole::Sales->value)
                    // Same reasoning: do not let the last admin demote themself.
                    ->disabled(fn (?User $record) => $record?->is(Auth::user()) ?? false)
                    ->helperText(fn (?User $record) => $record?->is(Auth::user())
                        ? 'You cannot change your own role.'
                        : null),

                TextInput::make('approval_limit')
                    ->label('Approval limit (KES)')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.01')
                    ->visible(fn (Get $get) => $get('role') === UserRole::Approver->value)
                    ->helperText('Leave blank for unlimited authority.'),

                Select::make('driver_id')
                    ->label('Linked driver record')
                    ->options(fn (?User $record) => Driver::query()
                        // Only unlinked drivers, plus whoever is already linked
                        // to this user, so the current value is never lost.
                        ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $record?->getKey() ?? 0))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn (Get $get) => $get('role') === UserRole::Driver->value)
                    ->required(fn (Get $get) => $get('role') === UserRole::Driver->value)
                    ->helperText('A driver login only shows the trips assigned to this driver record.')
                    ->dehydrated(false)
                    ->afterStateHydrated(fn ($component, ?User $record) => $component->state($record?->driver?->getKey())),
            ]),
        ]);
    }
}
