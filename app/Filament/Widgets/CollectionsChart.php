<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsTheDashboardRange;
use App\Models\MpesaTransaction;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

/**
 * Money actually collected, by day, over the last fortnight.
 *
 * Invoiced value and collected value are different questions — a good month
 * for sales can be a bad month for cash — so collections get their own chart
 * rather than a second series on the invoicing one.
 */
class CollectionsChart extends ChartWidget
{
    use ReadsTheDashboardRange;

    protected ?string $heading = 'Collections';

    protected ?string $description = 'M-Pesa receipts confirmed over the chosen period.';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return Auth::user()?->canViewPayments() ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $daily = $this->dailyTotals();

        return [
            'datasets' => [
                [
                    'label' => 'Received',
                    'data' => array_values($daily),
                    'borderColor' => '#15803d',
                    'backgroundColor' => 'rgba(21, 128, 61, 0.08)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                ],
            ],
            'labels' => array_keys($daily),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => false]],
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
                    'ticks' => ['font' => ['size' => 10], 'maxRotation' => 0],
                ],
            ],
        ];
    }

    /**
     * @return array<string, float>
     */
    protected function dailyTotals(): array
    {
        /*
         * The window comes from the filter bar. It used to be a hard-coded
         * fortnight, so moving the dates above changed every figure on the
         * dashboard except this one.
         *
         * Capped at 90 buckets: a year-long range would draw 365 columns into
         * a strip a few centimetres tall and read as noise.
         */
        $start = $this->rangeFrom();
        $end = $this->rangeUntil();
        $span = min(90, max(1, $start->diffInDays($end) + 1));

        $totals = MpesaTransaction::query()
            ->where('callback_type', MpesaTransaction::TYPE_CONFIRMATION)
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('CAST([created_at] AS DATE)')
            ->selectRaw('CAST([created_at] AS DATE) AS bucket, SUM(CAST([trans_amount] AS DECIMAL(18,2))) AS total')
            ->pluck('total', 'bucket')
            ->mapWithKeys(fn ($total, $bucket) => [
                Carbon::parse($bucket)->toDateString() => (float) $total,
            ]);

        $days = [];

        for ($offset = 0; $offset < $span; $offset++) {
            $day = $start->addDays($offset);
            $days[$day->format('d M')] = $totals[$day->toDateString()] ?? 0.0;
        }

        return $days;
    }
}
