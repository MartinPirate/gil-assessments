<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Models\Invoice;
use App\Services\InvoicePdf;
use App\Support\InvoiceCalculator;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('No.')
                    ->searchable(['doc_num'])
                    ->sortable(['doc_num']),

                TextColumn::make('posting_date')
                    ->label('Posting Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('customer_code')
                    ->label('Customer')
                    ->description(fn ($record) => $record->customer_name)
                    ->searchable(['customer_code', 'customer_name']),

                TextColumn::make('sales_employee_name')
                    ->label('Sales Employee')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('total_before_discount')
                    ->label('Before Disc.')
                    ->numeric(decimalPlaces: InvoiceCalculator::DOCUMENT_SCALE)
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('discount_percent')
                    ->label('Disc. %')
                    ->numeric(decimalPlaces: InvoiceCalculator::DOCUMENT_SCALE)
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('total_after_discount')
                    ->label('Total')
                    ->numeric(decimalPlaces: InvoiceCalculator::DOCUMENT_SCALE)
                    ->alignEnd()
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending Approval' => 'warning',
                        'Open' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('etr_barcode')
                    ->label('ETR Barcode')
                    ->placeholder('—')
                    // The point of storing it: finding the document from the
                    // paper receipt in your hand.
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('doc_num', 'desc')
            ->filters([
                TernaryFilter::make('requires_approval')
                    ->label('Approval required')
                    ->placeholder('All invoices')
                    ->trueLabel('Over the threshold')
                    ->falseLabel('Under the threshold'),

                /*
                 * One range picker instead of two loose date boxes: it carries
                 * the presets people actually reach for (today, this month,
                 * last month) and cannot express a backwards range.
                 */
                DateRangeFilter::make('posting_date')
                    ->label('Posting date')
                    ->placeholder('Any date'),
            ])
            /*
             * Buttons rather than bare icons. Rendered as icons they were easy
             * to miss entirely — a register where only the row under the mouse
             * looks actionable reads as though the rest are locked.
             */
            ->recordActions([
                ViewAction::make()
                    ->button()
                    ->outlined()
                    ->size('xs'),

                /*
                 * Renders the document and files it against the invoice, then
                 * hands it over. Stored rather than streamed so the file that
                 * was sent to a customer can be produced again unchanged.
                 *
                 * Drafts are excluded: a draft is not a document anybody should
                 * be sending out.
                 */
                Action::make('pdf')
                    ->label(fn (Invoice $record) => $record->hasPdf() ? 'PDF' : 'Generate PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->button()
                    ->outlined()
                    ->size('xs')
                    ->color('gray')
                    ->visible(fn (Invoice $record) => $record->doc_type !== Invoice::TYPE_DRAFT)
                    ->action(function (Invoice $record) {
                        $media = $record->pdf() ?? app(InvoicePdf::class)->render($record);

                        return response()->download($media->getPath(), $media->file_name);
                    }),
            ]);
    }
}
