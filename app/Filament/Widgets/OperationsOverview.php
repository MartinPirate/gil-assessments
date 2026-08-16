<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Widgets\Concerns\ReadsTheDashboardRange;
use App\Models\ApprovalRequest;
use App\Models\GateLog;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * The numbers a manager opens the system to see.
 *
 * Each stat is scoped to what the signed-in role is allowed to know about, so
 * a gate officer does not get a revenue readout.
 */
class OperationsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    // The stat band reads first, above the charts and the worklist.
    protected static ?int $sort = 1;

    /*
     * The stat row spans the full dashboard grid so the tiles sit four across
     * as one band, rather than inheriting the page's column count and wrapping
     * three-and-two.
     */
    use ReadsTheDashboardRange;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $stats = [];

        $from = $this->rangeFrom();
        $until = $this->rangeUntil();

        if ($user?->canSell() || $user?->canApprove()) {
            /*
             * Scoped to the chosen period rather than to today. The tile used
             * to read "Invoiced today" no matter what the filter bar said,
             * which made the two disagree in plain sight.
             */
            $invoiced = InvoiceResource::getEloquentQuery()->posted()->whereBetween('posting_date', [$from, $until]);
            $invoicedValue = (float) (clone $invoiced)->sum('document_total');

            $outstandingQuery = InvoiceResource::getEloquentQuery()->outstanding()->whereBetween('posting_date', [$from, $until]);
            $outstanding = (float) (clone $outstandingQuery)->sum('balance_due');

            $stats[] = Stat::make('Invoiced', 'KES '.number_format($invoicedValue, 2))
                ->description((clone $invoiced)->count().' documents · '.$this->rangeLabel())
                ->descriptionIcon('heroicon-m-document-text')
                ->chart($this->lastSevenDays(InvoiceResource::getEloquentQuery()->posted(), 'posting_date', 'document_total'))
                ->color('primary');

            $stats[] = Stat::make('Outstanding balance', 'KES '.number_format($outstanding, 2))
                ->description((clone $outstandingQuery)->count().' unpaid invoices raised in this period')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($this->lastSevenDays(InvoiceResource::getEloquentQuery()->outstanding(), 'posting_date', 'balance_due'))
                ->color($outstanding > 0 ? 'warning' : 'success');
        }

        if ($user?->canApprove()) {
            $pending = ApprovalRequest::query()->pending()->count();

            /*
             * Deliberately not scoped: a queue is what is waiting now, and
             * hiding a request because it was raised before the chosen start
             * date would mean somebody never sees it.
             */
            $stats[] = Stat::make('Awaiting approval', (string) $pending)
                ->description($pending > 0 ? 'Needs a decision — queue is live, not filtered' : 'Queue is clear')
                ->descriptionIcon($pending > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->chart($this->lastSevenDays(ApprovalRequest::query(), 'created_at'))
                ->color($pending > 0 ? 'warning' : 'success');
        }

        if ($user?->canOperateGate()) {
            $onSite = GateLog::query()->open()->count();

            // Also live: what is in the yard is what is in the yard.
            $stats[] = Stat::make('Vehicles on site', (string) $onSite)
                ->description('Currently gated in — live, not filtered')
                ->descriptionIcon('heroicon-m-truck')
                ->chart($this->lastSevenDays(GateLog::query(), 'time_in'))
                ->color($onSite > 0 ? 'success' : 'gray');
        }

        return $stats;
    }

    /**
     * A seven-point daily series for a stat's sparkline, oldest first.
     *
     * One grouped query rather than seven dated ones — this widget polls every
     * 60 seconds and renders up to five tiles, so a query per point per tile
     * would be 35 round trips a minute for a decoration.
     *
     * Days with no rows still have to appear, otherwise the line closes the
     * gap and a quiet Sunday reads as a smooth trend instead of a flat one.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  'posting_date'|'created_at'|'time_in'  $dateColumn
     * @param  'document_total'|'balance_due'|null  $sumColumn
     * @return array<int, float>
     */
    protected function lastSevenDays(Builder $query, string $dateColumn, ?string $sumColumn = null): array
    {
        /*
         * Both column names are matched against a fixed list before they reach
         * the raw expressions below. They come from this file rather than from
         * a request, but a whitelist keeps that true if someone later passes
         * one in from elsewhere.
         */
        if (! in_array($dateColumn, ['posting_date', 'created_at', 'time_in'], true)) {
            throw new InvalidArgumentException("Unsupported date column [{$dateColumn}].");
        }

        if ($sumColumn !== null && ! in_array($sumColumn, ['document_total', 'balance_due'], true)) {
            throw new InvalidArgumentException("Unsupported sum column [{$sumColumn}].");
        }

        $start = today()->subDays(6);
        $aggregate = $sumColumn === null ? 'COUNT(*)' : "SUM([{$sumColumn}])";

        $totals = $query
            ->whereDate($dateColumn, '>=', $start)
            ->groupByRaw("CAST([{$dateColumn}] AS DATE)")
            ->selectRaw("CAST([{$dateColumn}] AS DATE) AS bucket, {$aggregate} AS total")
            ->pluck('total', 'bucket')
            ->mapWithKeys(fn ($total, $bucket) => [
                // sqlsrv hands dates back as "Y-m-d H:i:s.000"; normalise so
                // the lookup below matches.
                Carbon::parse($bucket)->toDateString() => (float) $total,
            ]);

        return collect(range(0, 6))
            ->map(fn (int $offset): float => $totals[$start->copy()->addDays($offset)->toDateString()] ?? 0.0)
            ->all();
    }
}
