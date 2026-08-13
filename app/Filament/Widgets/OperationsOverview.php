<?php

namespace App\Filament\Widgets;

use App\Models\ApprovalRequest;
use App\Models\GateLog;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

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
                ->color('primary');

            $stats[] = Stat::make('Outstanding balance', 'KES '.number_format($outstanding, 2))
                ->description(Invoice::query()->outstanding()->count().' unpaid invoices')
                ->color($outstanding > 0 ? 'warning' : 'success');
        }

        if ($role?->canApprove()) {
            $pending = ApprovalRequest::query()->pending()->count();

            $stats[] = Stat::make('Awaiting approval', (string) $pending)
                ->description($pending > 0 ? 'Needs a decision' : 'Queue is clear')
                ->color($pending > 0 ? 'warning' : 'success');
        }

        if ($role?->canOperateGate()) {
            $onSite = GateLog::query()->open()->count();

            $stats[] = Stat::make('Vehicles on site', (string) $onSite)
                ->description('Currently gated in')
                ->color($onSite > 0 ? 'success' : 'gray');
        }

        if ($role?->canViewPayments()) {
            $unmatched = MpesaTransaction::query()
                ->where('callback_type', MpesaTransaction::TYPE_CONFIRMATION)
                ->where('allocation_status', MpesaTransaction::ALLOCATION_UNMATCHED)
                ->count();

            $stats[] = Stat::make('Unmatched receipts', (string) $unmatched)
                ->description($unmatched > 0 ? 'Need manual allocation' : 'All payments applied')
                ->color($unmatched > 0 ? 'danger' : 'success');
        }

        return $stats;
    }
}
