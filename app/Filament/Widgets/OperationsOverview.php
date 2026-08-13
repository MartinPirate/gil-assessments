<?php

namespace App\Filament\Widgets;

use App\Models\ApprovalRequest;
use App\Models\GateLog;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
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

    protected function getStats(): array
    {
        $role = Auth::user()?->role();
        $stats = [];

        if ($role?->canSell() || $role?->canApprove()) {
            $invoicedToday = (float) Invoice::query()
                ->posted()
                ->whereDate('posting_date', today())
                ->sum('document_total');

            $outstanding = (float) Invoice::query()->outstanding()->sum('balance_due');

            $stats[] = Stat::make('Invoiced today', 'KES '.number_format($invoicedToday, 2))
                ->description(Invoice::query()->posted()->whereDate('posting_date', today())->count().' documents')
                ->chart($this->lastSevenDays(Invoice::query()->posted(), 'posting_date', 'document_total'))
                ->color('primary');

            $stats[] = Stat::make('Outstanding balance', 'KES '.number_format($outstanding, 2))
                ->description(Invoice::query()->outstanding()->count().' unpaid invoices')
                ->chart($this->lastSevenDays(Invoice::query()->outstanding(), 'posting_date', 'balance_due'))
                ->color($outstanding > 0 ? 'warning' : 'success');
        }

        if ($role?->canApprove()) {
            $pending = ApprovalRequest::query()->pending()->count();

            $stats[] = Stat::make('Awaiting approval', (string) $pending)
                ->description($pending > 0 ? 'Needs a decision' : 'Queue is clear')
                ->chart($this->lastSevenDays(ApprovalRequest::query(), 'created_at'))
                ->color($pending > 0 ? 'warning' : 'success');
        }

        if ($role?->canOperateGate()) {
            $onSite = GateLog::query()->open()->count();

            $stats[] = Stat::make('Vehicles on site', (string) $onSite)
                ->description('Currently gated in')
                ->chart($this->lastSevenDays(GateLog::query(), 'time_in'))
                ->color($onSite > 0 ? 'success' : 'gray');
        }

        if ($role?->canViewPayments()) {
            $unmatched = MpesaTransaction::query()
                ->where('callback_type', MpesaTransaction::TYPE_CONFIRMATION)
                ->where('allocation_status', MpesaTransaction::ALLOCATION_UNMATCHED)
                ->count();

            $stats[] = Stat::make('Unmatched receipts', (string) $unmatched)
                ->description($unmatched > 0 ? 'Need manual allocation' : 'All payments applied')
                ->chart($this->lastSevenDays(
                    MpesaTransaction::query()->where('callback_type', MpesaTransaction::TYPE_CONFIRMATION),
                    'created_at',
                ))
                ->color($unmatched > 0 ? 'danger' : 'success');
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
