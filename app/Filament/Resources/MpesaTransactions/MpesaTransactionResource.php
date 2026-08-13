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

    /**
     * Money that arrived but has not been applied to a document.
     *
     * Unmatched and partial receipts are the ones that need a person: the
     * customer typed the wrong reference, or paid less than the invoice. A
     * matched receipt needs nobody, so it is not counted here.
     */
    public static function getNavigationBadge(): ?string
    {
        $waiting = static::unallocatedCount();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $waiting = static::unallocatedCount();

        return $waiting > 0
            ? "{$waiting} receipt(s) have not been applied to an invoice."
            : null;
    }

    protected static function unallocatedCount(): int
    {
        return MpesaTransaction::query()
            ->whereIn('allocation_status', [
                MpesaTransaction::ALLOCATION_UNMATCHED,
                MpesaTransaction::ALLOCATION_PARTIAL,
            ])
            ->count();
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
