<?php

namespace App\Filament\Resources\MpesaTransactions;

use App\Filament\Resources\MpesaTransactions\Pages\ListMpesaTransactions;
use App\Filament\Resources\MpesaTransactions\Tables\MpesaTransactionsTable;
use App\Models\MpesaTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only view of what the C2B callback endpoints have captured.
 */
class MpesaTransactionResource extends Resource
{
    protected static ?string $model = MpesaTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?string $navigationLabel = 'M-Pesa Transactions';

    protected static ?string $modelLabel = 'M-Pesa transaction';

    public static function table(Table $table): Table
    {
        return MpesaTransactionsTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->role()->canViewPayments() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMpesaTransactions::route('/'),
        ];
    }
}
