<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\OrderStage;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\OrderStageEvent;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orders — the same documents as the invoice register, read by where they have
 * got to rather than by what they are worth.
 *
 * The register answers an accounting question: what has been raised, for how
 * much, and is it approved. This answers an operations one: what is paid but
 * not yet dispatched, what is out on the road, what arrived and was never
 * rated. Same records, different question, so it is a second page on the same
 * resource rather than a second resource.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'Orders';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * A tab per stage, each carrying its own count.
     *
     * Counting once here and handing the number to the badge avoids each tab
     * re-running its own count query on every render.
     */
    public function getTabs(): array
    {
        $counts = OrderStageEvent::query()
            ->selectRaw('[stage], COUNT(DISTINCT [invoice_id]) AS total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $tabs = [
            'all' => Tab::make('All')
                ->badge(Invoice::query()->posted()->count()),
        ];

        foreach (OrderStage::track() as $stage) {
            $tabs[$stage->value] = Tab::make($stage->label())
                ->badge($counts[$stage->value] ?? 0)
                ->badgeColor($stage->color())
                ->icon($stage->icon())
                /*
                 * "Reached this stage", not "is sitting at it". An order that
                 * was delivered is still one that was paid, and hiding it from
                 * the Paid tab would make the tabs disagree with the timeline
                 * on the document itself.
                 */
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas(
                    'stageEvents',
                    fn (Builder $events) => $events->where('stage', $stage->value),
                ));
        }

        $tabs['cancelled'] = Tab::make('Cancelled')
            ->badge($counts[OrderStage::Cancelled->value] ?? 0)
            ->badgeColor('danger')
            ->icon(OrderStage::Cancelled->icon())
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas(
                'stageEvents',
                fn (Builder $events) => $events->where('stage', OrderStage::Cancelled->value),
            ));

        return $tabs;
    }

    /** Only posted documents are orders; a draft is not one yet. */
    protected function getTableQuery(): ?Builder
    {
        return InvoiceResource::getEloquentQuery()->posted();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newOrder')
                ->label('New order')
                ->icon('heroicon-m-plus')
                ->url(fn (): string => \App\Filament\Pages\ArInvoice::getUrl()),
        ];
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    /**
     * The three numbers above the tabs.
     *
     * @return array<class-string<\Filament\Widgets\Widget>>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\OrderSummary::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return ['default' => 1, 'md' => 3];
    }
}
