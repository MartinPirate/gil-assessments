<?php

namespace App\Filament\Resources\MpesaTransactions;

use App\Filament\Concerns\ScopesToOwnWork;
use App\Filament\Resources\MpesaTransactions\Pages\ListMpesaTransactions;
use App\Filament\Resources\MpesaTransactions\Tables\MpesaTransactionsTable;
use App\Models\MpesaTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Read-only view of what the C2B callback endpoints have captured.
 */
class MpesaTransactionResource extends Resource
{
    use ScopesToOwnWork;

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
        return Auth::user()?->canViewPayments() ?? false;
    }

    /**
     * Money that arrived but has not been applied to a document.
     *
     * Unmatched and partial receipts are the ones that need a person: the
     * customer typed the wrong reference, or paid less than the invoice. A
     * matched receipt needs nobody, so it is not counted here.
     */
    /**
     * Only for people who can actually see and place the unmatched money.
     *
     * The badge counts receipts waiting to be applied. A salesperson's view is
     * scoped to receipts that settled their own documents, and an unallocated
     * receipt settles nobody's — so the badge was reporting a queue they could
     * not open, over a table that read "No M-Pesa transactions".
     */
    public static function getNavigationBadge(): ?string
    {
        if (static::shouldScopeToOwn()) {
            return null;
        }

        $waiting = static::unallocatedCount();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        if (static::shouldScopeToOwn()) {
            return null;
        }

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

    /**
     * A salesperson sees the receipts that settled their own documents.
     *
     * Reached through the allocations, because that is the only thing tying a
     * receipt to a document — an unallocated payment belongs to nobody yet, so
     * it stays with the people whose job is to place it.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! static::shouldScopeToOwn()) {
            return $query;
        }

        return $query->whereHas(
            'allocations.invoice',
            fn (Builder $invoice) => static::scopeInvoicesToOwn($invoice),
        );
    }
}
