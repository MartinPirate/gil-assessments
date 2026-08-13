<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStage;
use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The documents that are actually waiting on somebody.
 *
 * A dashboard table listing the ten most recent invoices would mostly show
 * documents nobody has to touch. This one is filtered to the two states that
 * need a person — waiting for a decision, or delivered and still unpaid — so
 * an empty table genuinely means there is nothing to do.
 */
class NeedsAttentionTable extends TableWidget
{
    protected static ?string $heading = 'Needs attention';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $role = Auth::user()?->role();

        return ($role?->canSell() || $role?->canApprove()) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25])
            ->defaultSort('posting_date', 'desc')
            ->emptyStateHeading('Nothing needs attention')
            ->emptyStateDescription('Every document is either settled or on its way.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('document_number')
                    ->label('No.')
                    ->searchable(['doc_num'])
                    ->weight('semibold'),

                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->limit(28),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Invoice::STATUS_PENDING_APPROVAL => 'warning',
                        Invoice::STATUS_OPEN => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('balance_due')
                    ->label('Balance')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('KES ')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('posting_date')
                    ->label('Age')
                    ->since()
                    ->sortable(),

                // Why it is on this list at all, in words.
                TextColumn::make('issue')
                    ->label('Issue')
                    ->state(fn (Invoice $record): string => $record->status === Invoice::STATUS_PENDING_APPROVAL
                        ? 'Awaiting approval'
                        : 'Delivered, not settled')
                    ->color(fn (Invoice $record): string => $record->status === Invoice::STATUS_PENDING_APPROVAL
                        ? 'warning'
                        : 'danger')
                    ->weight('medium'),
            ]);
    }

    /**
     * @return Builder<Invoice>
     */
    protected function query(): Builder
    {
        return Invoice::query()
            ->posted()
            ->where(fn (Builder $query) => $query
                ->where('status', Invoice::STATUS_PENDING_APPROVAL)
                ->orWhere(fn (Builder $delivered) => $delivered
                    ->where('balance_due', '>', 0)
                    ->whereHas(
                        'stageEvents',
                        fn (Builder $stage) => $stage->where('stage', OrderStage::Delivered->value),
                    )));
    }
}
