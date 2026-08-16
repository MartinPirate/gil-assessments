<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStage;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Widgets\Concerns\ReadsTheDashboardRange;
use App\Filament\Widgets\Concerns\ShowsCommercialFigures;
use App\Models\OrderStageEvent;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The three figures above the Orders list: how many, how many still moving,
 * and what an average one is worth.
 *
 * All three answer for the period chosen in the filter bar, and each says how
 * it compares with the period of the same length before it — a count on its
 * own tells you nothing about whether things are getting better or worse.
 */
class OrderSummary extends StatsOverviewWidget
{
    use ReadsTheDashboardRange;
    use ShowsCommercialFigures;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        [$previousFrom, $previousUntil] = $this->previousRange();

        $current = $this->figuresFor($this->rangeFrom(), $this->rangeUntil());
        $previous = $this->figuresFor($previousFrom, $previousUntil);

        return [
            Stat::make('Orders', number_format($current['count']))
                ->description($this->trend(
                    'KES '.number_format($current['value'], 2).' raised',
                    $this->percentageChange($current['value'], $previous['value']),
                ))
                ->descriptionIcon($this->arrow($this->percentageChange($current['value'], $previous['value'])))
                ->icon('heroicon-o-document-text')
                ->color($this->tone($this->percentageChange($current['value'], $previous['value']))),

            Stat::make('Open orders', number_format($current['open']))
                ->description($current['open'] > 0
                    ? $current['open'].' of '.$current['count'].' not yet delivered'
                    : 'All delivered or cancelled')
                ->descriptionIcon($current['open'] > 0 ? 'heroicon-m-truck' : 'heroicon-m-check-circle')
                ->icon('heroicon-o-clock')
                ->color($current['open'] > 0 ? 'warning' : 'success'),

            Stat::make('Average order', 'KES '.number_format($current['average'], 2))
                ->description($this->trend(
                    'Across '.$current['count'].' posted documents',
                    $this->percentageChange($current['average'], $previous['average']),
                ))
                ->descriptionIcon($this->arrow($this->percentageChange($current['average'], $previous['average'])))
                ->icon('heroicon-o-calculator')
                ->color($this->tone($this->percentageChange($current['average'], $previous['average']))),
        ];
    }

    /**
     * @return array{count: int, value: float, open: int, average: float}
     */
    protected function figuresFor(CarbonImmutable $from, CarbonImmutable $until): array
    {
        /*
         * Through the resource query, so the cards agree with the register
         * beneath them: a salesperson's "Orders 12" over three visible rows is
         * worse than no card at all.
         */
        $orders = InvoiceResource::getEloquentQuery()
            ->posted()
            ->whereBetween('posting_date', [$from, $until]);

        $count = (clone $orders)->count();
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

        return [
            'count' => $count,
            'value' => $value,
            'open' => (clone $orders)->whereNotIn('id', $settled)->count(),
            // Guard the division: an empty period would otherwise divide by zero.
            'average' => $count > 0 ? $value / $count : 0.0,
        ];
    }

    protected function trend(string $description, ?float $change): string
    {
        if ($change === null) {
            return $description;
        }

        return sprintf('%s%s%% vs previous period', $change >= 0 ? '+' : '', $change);
    }

    protected function arrow(?float $change): ?string
    {
        return match (true) {
            $change === null => null,
            $change > 0 => 'heroicon-m-arrow-trending-up',
            $change < 0 => 'heroicon-m-arrow-trending-down',
            default => 'heroicon-m-minus-small',
        };
    }

    /**
     * Grey when there is nothing to compare against, rather than a green tick
     * implying growth nobody measured.
     */
    protected function tone(?float $change): string
    {
        return match (true) {
            $change === null => 'gray',
            $change > 0 => 'success',
            $change < 0 => 'danger',
            default => 'gray',
        };
    }
}
