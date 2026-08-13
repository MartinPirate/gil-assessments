<?php

namespace App\Filament\Resources\Approvals\Tables;

use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ApprovalRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.document_number')
                    ->label('Invoice')
                    ->weight('bold')
                    ->searchable(['doc_num'])
                    ->url(fn (ApprovalRequest $r) => $r->invoice
                        ? \App\Filament\Resources\Invoices\InvoiceResource::getUrl('view', ['record' => $r->invoice])
                        : null),

                TextColumn::make('invoice.customer_name')
                    ->label('Customer')
                    ->description(fn (ApprovalRequest $r) => $r->invoice?->customer_code)
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 3)
                    ->prefix('KES ')
                    ->alignEnd()
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('threshold')
                    ->label('Threshold')
                    ->numeric(decimalPlaces: 0)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('requester.name')->label('Requested By')->toggleable(),

                TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ApprovalRequest::STATUS_APPROVED => 'success',
                        ApprovalRequest::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('decider.name')->label('Decided By')->placeholder('—')->toggleable(),

                TextColumn::make('decided_at')
                    ->label('Decided')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('decision_reason')
                    ->label('Reason')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        ApprovalRequest::STATUS_PENDING => 'Pending',
                        ApprovalRequest::STATUS_APPROVED => 'Approved',
                        ApprovalRequest::STATUS_REJECTED => 'Rejected',
                    ])
                    ->default(ApprovalRequest::STATUS_PENDING),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ApprovalRequest $r) => $r->status === ApprovalRequest::STATUS_PENDING)
                    // A decision is not reversible, so make it deliberate.
                    ->requiresConfirmation()
                    ->modalHeading(fn (ApprovalRequest $r) => "Approve {$r->invoice?->document_number}?")
                    ->modalDescription(fn (ApprovalRequest $r) => 'Amount: KES '.number_format((float) $r->amount, 2))
                    ->schema([
                        Textarea::make('reason')->label('Note (optional)')->rows(2)->maxLength(1000),
                    ])
                    ->action(function (ApprovalRequest $record, array $data) {
                        app(ApprovalService::class)->approve($record, Auth::user(), $data['reason'] ?? null);

                        Notification::make()
                            ->title("{$record->invoice?->document_number} approved")
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ApprovalRequest $r) => $r->status === ApprovalRequest::STATUS_PENDING)
                    ->modalHeading(fn (ApprovalRequest $r) => "Reject {$r->invoice?->document_number}?")
                    ->schema([
                        // Required: a rejected document must say why.
                        Textarea::make('reason')
                            ->label('Reason for rejection')
                            ->rows(3)
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (ApprovalRequest $record, array $data) {
                        app(ApprovalService::class)->reject($record, Auth::user(), $data['reason']);

                        Notification::make()
                            ->title("{$record->invoice?->document_number} rejected")
                            ->danger()
                            ->send();
                    }),
            ]);
    }
}
