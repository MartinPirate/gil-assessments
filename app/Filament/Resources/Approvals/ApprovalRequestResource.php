<?php

namespace App\Filament\Resources\Approvals;

use App\Filament\Resources\Approvals\Pages\ListApprovalRequests;
use App\Filament\Resources\Approvals\Tables\ApprovalRequestsTable;
use App\Models\ApprovalRequest;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * The queue behind the "Invoice will go for approval" label.
 */
class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Approvals';

    protected static ?string $modelLabel = 'approval request';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Auth::user()?->role()->canApprove() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return ApprovalRequestsTable::configure($table);
    }

    /** How many documents are waiting on a decision. */
    public static function getNavigationBadge(): ?string
    {
        $pending = ApprovalRequest::query()->pending()->count();
        $stuck = static::stuckInvoiceCount();

        if ($pending === 0 && $stuck === 0) {
            return null;
        }

        // A stuck document is worse than a queued one: it shows as pending on
        // the invoice with nothing to decide, so it is surfaced separately
        // rather than hidden behind a filtered list.
        return $stuck > 0 ? "{$pending} (+{$stuck} stuck)" : (string) $pending;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::stuckInvoiceCount() > 0 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $stuck = static::stuckInvoiceCount();

        return $stuck > 0
            ? "{$stuck} invoice(s) are Pending Approval with no open request. Run: php artisan invoices:repair-approvals"
            : null;
    }

    /**
     * Invoices marked pending that have nothing in the queue to decide.
     */
    public static function stuckInvoiceCount(): int
    {
        return Invoice::query()
            ->where('status', Invoice::STATUS_PENDING_APPROVAL)
            ->whereDoesntHave('approvalRequests', fn ($q) => $q->where('status', ApprovalRequest::STATUS_PENDING))
            ->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalRequests::route('/'),
        ];
    }
}
