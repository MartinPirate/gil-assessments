<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\OrderStage;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\OrderLifecycleService;
use App\Support\InvoiceCalculator;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use LaBoiteACode\FilamentActivityTimeline\Infolists\ActivityTimelineEntry;

/**
 * Full document view: header, lines and totals, mirroring the entry screen.
 */
class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            // One column: the line grid needs the full width, otherwise it is
            // squeezed into half the page and scrolls out of view.
            ->columns(1)
            ->components([
                Section::make('Document')->schema([
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        TextEntry::make('document_number')->label('No.'),
                        TextEntry::make('posting_date')->date('d/m/Y')->label('Posting Date'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Pending Approval' ? 'warning' : 'success'),
                        TextEntry::make('customer_code')->label('Customer Code'),
                        TextEntry::make('customer_name')->label('Customer Name'),
                        TextEntry::make('currency')->label('Currency'),
                        TextEntry::make('sales_employee_name')->label('Sales Employee'),
                        TextEntry::make('creator.name')->label('Created By')->placeholder('—'),
                        TextEntry::make('created_at')->dateTime('d/m/Y H:i')->label('Created At'),
                    ]),

                    TextEntry::make('remarks')->columnSpanFull(),

                    TextEntry::make('etr_barcode')
                        ->label('ETR Receipt Barcode')
                        ->placeholder('Not scanned')
                        ->copyable()
                        ->helperText(fn (Invoice $record): ?string => $record->etr_scanned_at
                            ? 'Scanned '.$record->etr_scanned_at->format('d/m/Y H:i')
                            : null),

                    TextEntry::make('approval_note')
                        ->hiddenLabel()
                        ->state(fn (Invoice $record) => 'Invoice will go for approval – Amount: '
                            .number_format((float) $record->total_after_discount, 2))
                        ->badge()
                        ->color('warning')
                        ->visible(fn (Invoice $record) => $record->requires_approval)
                        ->columnSpanFull(),
                ]),

                Section::make('Contents')->columnSpanFull()->schema([
                    RepeatableEntry::make('lines')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Item No.'),
                            TableColumn::make('Description'),
                            TableColumn::make('Qty')->alignEnd(),
                            TableColumn::make('UoM'),
                            TableColumn::make('Price Before Disc.')->alignEnd(),
                            TableColumn::make('Disc. %')->alignEnd(),
                            TableColumn::make('Price After Disc.')->alignEnd(),
                            TableColumn::make('Total')->alignEnd(),
                        ])
                        ->schema([
                            TextEntry::make('item.item_no')->hiddenLabel()->placeholder('—'),
                            TextEntry::make('item_description')->hiddenLabel(),
                            /*
                             * Read at the same precision they were entered at.
                             * The scales come from InvoiceCalculator rather
                             * than being chosen here, so this view and the A/R
                             * Invoice grid cannot drift apart and show the same
                             * line two different ways.
                             */
                            TextEntry::make('quantity')->hiddenLabel()
                                ->numeric(decimalPlaces: InvoiceCalculator::DISPLAY_SCALES['quantity']),
                            TextEntry::make('item.uom')->hiddenLabel()->placeholder('—'),
                            TextEntry::make('price_before_discount')->hiddenLabel()
                                ->numeric(decimalPlaces: InvoiceCalculator::DISPLAY_SCALES['price_before_discount']),
                            TextEntry::make('discount_percent')->hiddenLabel()
                                ->numeric(decimalPlaces: InvoiceCalculator::DISPLAY_SCALES['discount_percent']),
                            TextEntry::make('price_after_discount')->hiddenLabel()
                                ->numeric(decimalPlaces: InvoiceCalculator::DISPLAY_SCALES['price_after_discount']),
                            TextEntry::make('line_total')->hiddenLabel()
                                ->numeric(decimalPlaces: InvoiceCalculator::DISPLAY_SCALES['line_total']),
                        ]),
                ]),

                Section::make('Totals')->columnSpanFull()->schema([
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        TextEntry::make('total_before_discount')
                            ->numeric(decimalPlaces: InvoiceCalculator::DISPLAY_SCALES['total_before_discount'])->prefix('KES '),
                        TextEntry::make('discount_percent')
                            ->numeric(decimalPlaces: InvoiceCalculator::DISPLAY_SCALES['discount_percent'])->suffix(' %'),
                        TextEntry::make('total_after_discount')
                            ->numeric(decimalPlaces: InvoiceCalculator::DISPLAY_SCALES['total_after_discount'])->prefix('KES ')->weight('bold'),
                    ]),
                ]),

                /*
             * Only when there is something to show. A section headed "Freight
             * charges" over an empty table on every document that had none
             * would be furniture.
             */
                Section::make('Freight charges')
                    ->description('What the delivery charge on this document is made of.')
                    ->columnSpanFull()
                    ->visible(fn (Invoice $record) => $record->freightCharges()->exists())
                    ->schema([
                        ViewEntry::make('freight')
                            ->hiddenLabel()
                            ->view('filament.partials.invoice-freight-charges'),
                    ]),

                Section::make('Order lifecycle')
                    ->description('Where this order has got to, and how it got there.')
                    ->columnSpanFull()
                    ->schema([
                        // The track across the top: placed -> paid -> dispatched ->
                        // delivered -> rated, with everything reached so far filled in.
                        ViewEntry::make('order_track')
                            ->hiddenLabel()
                            ->view('filament.partials.order-track')
                            ->columnSpanFull(),

                        /*
                     * The plugin's default sentence is built for a change log
                     * — "System ran "paid" on Invoice #2" — which describes the
                     * mechanism rather than the business. Each stage gets the
                     * sentence a person would actually say about it.
                     *
                     * None of them name the causer: the plugin already prints
                     * it on the line underneath, and a stage nobody performed
                     * would otherwise read "placed by System".
                     */
                        ActivityTimelineEntry::make('lifecycle')
                            ->source('order')
                            ->heading('History')
                            ->perPage(10)
                            ->loadMore()
                            ->eventLabels([
                                'placed' => 'Placed',
                                'approved' => 'Approved',
                                'paid' => 'Paid',
                                'dispatched' => 'Dispatched',
                                'delivered' => 'Delivered',
                                'rated' => 'Rated',
                                'cancelled' => 'Cancelled',
                            ])
                            ->eventSentence('placed', 'The order was placed')
                            ->eventSentence('approved', 'The document cleared approval')
                            ->eventSentence('paid', 'Payment settled the document in full')
                            ->eventSentence('dispatched', 'The goods left the yard')
                            ->eventSentence('delivered', 'The goods were delivered')
                            ->eventSentence('rated', 'The customer rated the delivery')
                            ->eventSentence('cancelled', 'The order was cancelled')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Rating closes the loop: it is the only stage a customer, rather than the
     * business, causes. Offered once the goods have actually arrived — rating a
     * delivery that has not happened would be meaningless.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('rateDelivery')
                ->label('Rate delivery')
                ->icon('heroicon-o-star')
                ->color('primary')
                ->visible(fn (Invoice $record): bool => app(OrderLifecycleService::class)
                    ->hasReached($record, OrderStage::Delivered))
                ->schema([
                    Radio::make('delivery_rating')
                        ->label('How was the delivery?')
                        ->options([
                            5 => 'Excellent',
                            4 => 'Good',
                            3 => 'Acceptable',
                            2 => 'Poor',
                            1 => 'Unacceptable',
                        ])
                        ->required(),

                    Textarea::make('delivery_rating_comment')
                        ->label('Comments')
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->action(function (Invoice $record, array $data): void {
                    $rating = (int) $data['delivery_rating'];
                    $comment = $data['delivery_rating_comment'] ?? null;

                    $record->update([
                        'delivery_rating' => $rating,
                        'delivery_rating_comment' => $comment,
                        'delivery_rated_at' => now(),
                    ]);

                    app(OrderLifecycleService::class)->record(
                        $record,
                        OrderStage::Rated,
                        note: "Rated {$rating} out of 5.".($comment ? " \"{$comment}\"" : ''),
                        meta: ['rating' => $rating],
                    );

                    Notification::make()
                        ->title('Thank you — the rating was recorded.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
