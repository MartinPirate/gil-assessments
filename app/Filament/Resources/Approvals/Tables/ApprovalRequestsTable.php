<?php

namespace App\Filament\Resources\Approvals\Tables;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use App\Support\InvoiceCalculator;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
                        ? InvoiceResource::getUrl('view', ['record' => $r->invoice])
                        : null),

                TextColumn::make('invoice.customer_name')
                    ->label('Customer')
                    ->description(fn (ApprovalRequest $r) => $r->invoice?->customer_code)
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    // Three places, like every other figure on the document —
                    // InvoiceCalculator holds the scale so this column and the
                    // register cannot disagree about the same amount.
                    ->numeric(decimalPlaces: InvoiceCalculator::DOCUMENT_SCALE)
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
                    // Said before the click, not after: an approver whose
                    // ceiling this breaches cannot decide it, and finding that
                    // out by pressing Confirm and watching nothing happen is
                    // how a working guard reads as a broken button.
                    ->disabled(fn (ApprovalRequest $r) => ! (Auth::user()?->canApproveAmount((float) $r->amount) ?? false))
                    ->tooltip(fn (ApprovalRequest $r) => (Auth::user()?->canApproveAmount((float) $r->amount) ?? false)
                        ? null
                        : 'KES '.number_format((float) $r->amount, 2).' is above your approval limit of KES '
                            .number_format((float) Auth::user()?->approval_limit, 2).'. Someone with a higher ceiling must decide this one.')
                    ->action(function (ApprovalRequest $record, array $data, Action $action) {
                        try {
                            app(ApprovalService::class)->approve($record, Auth::user(), $data['reason'] ?? null);
                        } catch (ValidationException $e) {
                            /*
                             * The service reports refusals as validation
                             * messages keyed 'request', and there is no form
                             * field by that name — so Filament had nowhere to
                             * put them and the modal sat there saying nothing.
                             * Shown as a notification instead.
                             */
                            Notification::make()
                                ->title('Not approved')
                                ->body(collect($e->errors())->flatten()->implode(' '))
                                ->danger()
                                ->send();

                            $action->halt();

                            return;
                        }

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
                    ->disabled(fn (ApprovalRequest $r) => ! (Auth::user()?->canApproveAmount((float) $r->amount) ?? false))
                    ->tooltip(fn (ApprovalRequest $r) => (Auth::user()?->canApproveAmount((float) $r->amount) ?? false)
                        ? null
                        : 'KES '.number_format((float) $r->amount, 2).' is above your approval limit.')
                    ->action(function (ApprovalRequest $record, array $data, Action $action) {
                        try {
                            app(ApprovalService::class)->reject($record, Auth::user(), $data['reason']);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Not rejected')
                                ->body(collect($e->errors())->flatten()->implode(' '))
                                ->danger()
                                ->send();

                            $action->halt();

                            return;
                        }

                        Notification::make()
                            ->title("{$record->invoice?->document_number} rejected")
                            ->danger()
                            ->send();
                    }),
            ]);
    }
}
