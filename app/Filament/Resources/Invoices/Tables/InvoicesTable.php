<?php

namespace App\Filament\Resources\Invoices\Tables;

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
                    ->numeric(decimalPlaces: 3)
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('discount_percent')
                    ->label('Disc. %')
                    ->numeric(decimalPlaces: 3)
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('total_after_discount')
                    ->label('Total')
                    ->numeric(decimalPlaces: 3)
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
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
