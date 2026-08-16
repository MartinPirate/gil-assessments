<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
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
                /*
                 * The role is a Laratrust row rather than a column, so it is
                 * read from the pivot here and written back by the create and
                 * edit pages once the user exists.
                 */
                Select::make('role')
                    ->options(UserRole::options())
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->default(UserRole::Sales->value)
                    ->afterStateHydrated(fn ($component, ?User $record) => $component->state($record?->role()?->value))
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
                    /*
                     * Shown for any role that carries the approval permission,
                     * rather than for one named role — the ceiling belongs to
                     * whoever may approve.
                     */
                    ->visible(fn (Get $get) => in_array(
                        Permission::ApproveDocuments,
                        UserRole::tryFrom((string) $get('role'))?->permissions() ?? [],
                        true,
                    ))
                    ->helperText('Leave blank for unlimited authority.'),

                /*
                 * Read-only on purpose. Every driver record must have a login,
                 * so a driver cannot be released from here — doing so would
                 * leave the driver row pointing at nothing, which the database
                 * no longer allows. The pairing is made once, on the Drivers
                 * screen, where the driver and the account are created together.
                 */
                Placeholder::make('driver_record')
                    ->label('Linked driver record')
                    ->content(fn (?User $record) => $record?->driver?->name
                        ?? 'None. Driver records are created together with their login under Master Data → Drivers.')
                    ->visible(fn (Get $get) => $get('role') === UserRole::Driver->value),
            ]),
        ]);
    }
}
