<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Widgets\Concerns\ShowsCommercialFigures;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

/**
 * Invoiced value, this year against last.
 *
 * Two series rather than one: a single line answers "how much did we invoice
 * in March", which nobody asks. The question is whether March was better than
 * last March, and that needs the comparison drawn alongside it.
 */
class InvoicedTrendChart extends ChartWidget
{
    use ShowsCommercialFigures;

    protected ?string $heading = 'Invoiced value, year on year';

    protected ?string $description = 'Monthly totals against the same month last year.';

    protected static ?int $sort = 2;

    // Half the dashboard's four columns, so the two charts pair up.
    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $thisYear = $this->monthlyTotals(now()->year);
        $lastYear = $this->monthlyTotals(now()->year - 1);

        return [
            'datasets' => [
                [
                    'label' => (string) now()->year,
                    'data' => array_values($thisYear),
                    'borderColor' => '#e2571f',
                    'backgroundColor' => 'rgba(226, 87, 31, 0.08)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                ],
                [
                    'label' => (string) (now()->year - 1),
                    'data' => array_values($lastYear),
                    'borderColor' => '#b8b1a6',
                    'backgroundColor' => 'transparent',
                    'borderWidth' => 1.5,
                    'borderDash' => [4, 4],
                    'fill' => false,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                ],
            ],
            'labels' => array_keys($thisYear),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'boxWidth' => 6,
                        'boxHeight' => 6,
                        'padding' => 16,
                        'font' => ['size' => 11],
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'border' => ['display' => false],
                    'grid' => ['color' => 'rgba(28, 27, 25, 0.05)'],
                    'ticks' => ['font' => ['size' => 10], 'maxTicksLimit' => 5],
                ],
                'x' => [
                    'border' => ['display' => false],
                    'grid' => ['display' => false],
                    'ticks' => ['font' => ['size' => 10]],
                ],
            ],
        ];
    }

    /**
     * Twelve months of a year, keyed by short month name.
     *
     * One grouped query per year: a query per month would be 24 round trips
     * for a chart that redraws on every dashboard load.
     *
     * @return array<string, float>
     */
    protected function monthlyTotals(int $year): array
    {
        // Same set as everything else on the page.
        $totals = InvoiceResource::getEloquentQuery()
            ->posted()
            ->whereYear('posting_date', $year)
            ->groupByRaw('MONTH([posting_date])')
            ->selectRaw('MONTH([posting_date]) AS m, SUM([document_total]) AS total')
            ->pluck('total', 'm');

        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            // Months with nothing invoiced are zeroes, not gaps — a missing
            // point would let the line skip the month entirely.
            $label = Carbon::create($year, $month, 1)->format('M');
            $months[$label] = (float) ($totals[$month] ?? 0);
        }

        return $months;
    }
}
