<?php

namespace App\Filament\Resources\VatCodes\Schemas;

use App\Models\VatCode;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VatCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(16)
                    ->unique(ignoreRecord: true)
                    ->helperText('As it appears on the document — O0, V16, V8, E.'),

                TextInput::make('name')
                    ->required()
                    ->maxLength(100),

                /*
                 * Bounded rather than merely numeric: a rate is a percentage,
                 * and a stray 1600 would quietly multiply every tax figure on
                 * the next invoice by a hundred.
                 */
                TextInput::make('rate')
                    ->label('Rate (%)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step('0.001')
                    ->default(0)
                    ->helperText('Applied to new lines only. Documents already posted keep the rate they were charged at.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive codes stay on the documents that used them but are no longer offered.'),

                Toggle::make('is_default')
                    ->label('Default for new lines')
                    ->default(false)
                    ->helperText(fn (?VatCode $record) => $record?->is_default
                        ? 'This is the code new lines start with.'
                        : 'Turning this on takes the default away from whichever code holds it now.'),
            ]);
    }
}
