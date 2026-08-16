<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('user_name')
                    ->label('Who')
                    ->description(fn (AuditLog $r) => $r->user_role)
                    ->placeholder('system')
                    ->searchable(),

                TextColumn::make('event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AuditLog::CREATED => 'success',
                        AuditLog::DELETED => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('model_name')
                    ->label('Record')
                    ->description(fn (AuditLog $r) => $r->auditable_label)
                    ->searchable(['auditable_type', 'auditable_label']),

                TextColumn::make('changes_count')
                    ->label('Fields')
                    ->state(fn (AuditLog $r) => count($r->changes_list))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        AuditLog::CREATED => 'Created',
                        AuditLog::UPDATED => 'Updated',
                        AuditLog::DELETED => 'Deleted',
                    ]),

                SelectFilter::make('auditable_type')
                    ->label('Record type')
                    ->options(fn () => AuditLog::query()
                        ->distinct()
                        ->pluck('auditable_type', 'auditable_type')
                        ->mapWithKeys(fn ($v, $k) => [$k => class_basename($v)])
                        ->all()),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name'),

                Filter::make('when')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('diff')
                    ->label('View changes')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (AuditLog $r) => "{$r->event} {$r->model_name} {$r->auditable_label}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (AuditLog $r) => view('filament.partials.audit-diff', ['log' => $r])),
            ]);
    }
}
