<?php

namespace App\Filament\Pages\Resources\VatCodes;

use App\Filament\Pages\Resources\VatCodes\Pages\CreateVatCode;
use App\Filament\Pages\Resources\VatCodes\Pages\EditVatCode;
use App\Filament\Pages\Resources\VatCodes\Pages\ListVatCodes;
use App\Filament\Pages\Resources\VatCodes\Schemas\VatCodeForm;
use App\Filament\Pages\Resources\VatCodes\Tables\VatCodesTable;
use App\Models\VatCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * The VAT codes a line may be posted against.
 *
 * These live in a table rather than an enum because the rate is legislation,
 * not policy: Kenya moved the standard rate from 16% to 14% in April 2020 and
 * back in January 2021. That is an edit somebody in finance makes on a
 * Tuesday, and this is the screen they make it on.
 *
 * Changing a rate here only affects documents raised afterwards — every line
 * already posted keeps its own vat_rate, so a tax record cannot be restated
 * from behind.
 */
class VatCodeResource extends Resource
{
    protected static ?string $model = VatCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'code';

    public static function canAccess(): bool
    {
        return Auth::user()?->canAdminister() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return VatCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VatCodesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVatCodes::route('/'),
            'create' => CreateVatCode::route('/create'),
            'edit' => EditVatCode::route('/{record}/edit'),
        ];
    }
}
