<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ListOrders;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only register of posted A/R invoices.
 *
 * Documents are created on the A/R Invoice screen, not here, so this resource
 * intentionally exposes no create or edit page — a posted invoice is an
 * accounting record, not an editable row.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Invoice Register';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->role()->canSell() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Orders gets its own entry in the sidebar.
     *
     * It is a page on this resource rather than a resource of its own, so
     * Filament would otherwise only surface the register. Registering the
     * navigation item explicitly puts both where people look for them, and the
     * item stays highlighted while any of the order tabs is open.
     */
    public static function getNavigationItems(): array
    {
        $ordersRoute = static::getRouteBaseName().'.orders';

        return [
            // The register's own item would otherwise stay lit on the orders
            // route too, since Filament matches the whole resource by prefix.
            ...collect(parent::getNavigationItems())
                ->each(fn (\Filament\Navigation\NavigationItem $item) => $item->isActiveWhen(
                    fn (): bool => request()->routeIs(static::getRouteBaseName().'.*')
                        && ! request()->routeIs($ordersRoute),
                ))
                ->all(),

            \Filament\Navigation\NavigationItem::make('Orders')
                ->group('Sales')
                ->icon(Heroicon::OutlinedShoppingBag)
                ->sort(1)
                ->url(fn (): string => static::getUrl('orders'))
                ->isActiveWhen(fn (): bool => request()->routeIs($ordersRoute))
                ->visible(fn (): bool => static::canViewAny()),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            // Same records, read by where they have got to rather than by what
            // they are worth. Registered as a page on this resource so both
            // views share one query, one policy and one table definition.
            'orders' => ListOrders::route('/orders'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
