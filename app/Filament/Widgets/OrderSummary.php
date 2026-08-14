<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStage;
use App\Models\Invoice;
use App\Models\OrderStageEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The three figures above the Orders list: how many, how many still moving,
 * and what an average one is worth.
 */
class OrderSummary extends StatsOverviewWidget
{
    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $orders = Invoice::query()->posted();

        $total = (clone $orders)->count();
        $value = (float) (clone $orders)->sum('document_total');

        /*
         * Open means placed but not yet delivered or cancelled. Derived from
         * the stage events rather than from `status`, because a document can be
         * Closed on the ledger while the goods are still on a lorry.
         */
        $settled = OrderStageEvent::query()
            ->whereIn('stage', [OrderStage::Delivered->value, OrderStage::Cancelled->value])
            ->distinct()
            ->pluck('invoice_id');

        $open = (clone $orders)->whereNotIn('id', $settled)->count();

        // Guard the division: an empty system would otherwise divide by zero.
        $average = $total > 0 ? $value / $total : 0.0;

        return [
            Stat::make('Orders', number_format($total))
                ->description('KES '.number_format($value, 2).' raised')
                ->color('primary'),

            Stat::make('Open orders', number_format($open))
                ->description($open > 0 ? 'Not yet delivered' : 'All delivered or cancelled')
                ->color($open > 0 ? 'warning' : 'success'),

            Stat::make('Average order', 'KES '.number_format($average, 2))
                ->description('Across every posted document')
                ->color('gray'),
        ];
    }
}
