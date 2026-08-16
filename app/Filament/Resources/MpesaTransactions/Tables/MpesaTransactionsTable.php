<?php

namespace App\Filament\Resources\MpesaTransactions\Tables;

use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Models\PaymentAllocation;
use App\Services\PaymentAllocationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MpesaTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('trans_id')
                    ->label('TransID')
                    ->weight('bold')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('trans_amount')
                    ->label('Amount')
                    ->alignEnd()
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : 'KES '.number_format((float) $state, 2))
                    ->sortable(),

                TextColumn::make('msisdn')
                    ->label('MSISDN')
                    ->description(fn (MpesaTransaction $r) => $r->payer_name ?: null)
                    ->searchable(),

                TextColumn::make('bill_ref_number')
                    ->label('Account')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('business_short_code')
                    ->label('Short Code')
                    ->toggleable(),

                TextColumn::make('transacted_at')
                    ->label('Transacted')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),

                TextColumn::make('allocation_status')
                    ->label('Allocation')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        MpesaTransaction::ALLOCATION_MATCHED => 'success',
                        MpesaTransaction::ALLOCATION_PARTIAL => 'warning',
                        MpesaTransaction::ALLOCATION_UNMATCHED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('allocations.invoice.document_number')
                    ->label('Applied To')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('callback_type')
                    ->label('Callback')
                    ->badge()
                    ->color(fn (string $state) => $state === MpesaTransaction::TYPE_CONFIRMATION ? 'success' : 'gray'),

                TextColumn::make('received_at')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                SelectFilter::make('callback_type')
                    ->options([
                        MpesaTransaction::TYPE_CONFIRMATION => 'Confirmation',
                        MpesaTransaction::TYPE_VALIDATION => 'Validation',
                    ]),

                SelectFilter::make('allocation_status')
                    ->label('Allocation')
                    ->options([
                        MpesaTransaction::ALLOCATION_MATCHED => 'Matched',
                        MpesaTransaction::ALLOCATION_PARTIAL => 'Partially applied',
                        MpesaTransaction::ALLOCATION_UNMATCHED => 'Unmatched',
                    ]),
            ])
            ->recordActions([
                // Customers mistype the invoice number, so auto-matching will
                // always miss some receipts. This is how a human clears them.
                Action::make('allocate')
                    ->label('Allocate')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn (MpesaTransaction $r) => $r->callback_type === MpesaTransaction::TYPE_CONFIRMATION
                        && $r->unallocated_amount > 0)
                    ->modalHeading(fn (MpesaTransaction $r) => "Allocate {$r->trans_id}")
                    ->modalDescription(fn (MpesaTransaction $r) => 'Unallocated: KES '
                        .number_format($r->unallocated_amount, 2).' from '.($r->payer_name ?: $r->msisdn))
                    ->schema(fn (MpesaTransaction $r) => [
                        Select::make('invoice_id')
                            ->label('Invoice')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Invoice::query()
                                ->outstanding()
                                ->where(fn ($q) => $q->where('doc_num', 'like', "%{$search}%")
                                    ->orWhere('customer_name', 'like', "%{$search}%")
                                    ->orWhere('customer_code', 'like', "%{$search}%"))
                                ->limit(25)
                                ->get()
                                ->mapWithKeys(fn (Invoice $i) => [
                                    $i->id => $i->document_number.' — '.$i->customer_name
                                        .' (balance '.number_format((float) $i->balance_due, 2).')',
                                ])
                                ->all())
                            ->getOptionLabelUsing(fn ($value) => Invoice::find($value)?->document_number),

                        TextInput::make('amount')
                            ->label('Amount to apply')
                            ->numeric()
                            ->required()
                            ->minValue(0.001)
                            ->step('0.001')
                            ->default($r->unallocated_amount)
                            ->maxValue($r->unallocated_amount)
                            ->helperText('Cannot exceed the unallocated balance of this receipt.'),
                    ])
                    ->action(function (MpesaTransaction $record, array $data) {
                        $invoice = Invoice::findOrFail($data['invoice_id']);

                        app(PaymentAllocationService::class)->allocate(
                            $record,
                            $invoice,
                            (float) $data['amount'],
                            PaymentAllocation::MATCHED_MANUAL,
                            Auth::id(),
                        );

                        Notification::make()
                            ->title('Payment allocated')
                            ->body("{$record->trans_id} applied to {$invoice->document_number}.")
                            ->success()
                            ->send();
                    }),

                // The raw body is kept so nothing Safaricom sends is lost;
                // this exposes it for support/debugging.
                Action::make('raw')
                    ->label('Raw payload')
                    ->icon('heroicon-o-code-bracket')
                    ->modalHeading(fn (MpesaTransaction $r) => "Raw callback — {$r->trans_id}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (MpesaTransaction $r) => view('filament.partials.json-viewer', [
                        'json' => json_encode($r->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ])),
            ]);
    }
}
