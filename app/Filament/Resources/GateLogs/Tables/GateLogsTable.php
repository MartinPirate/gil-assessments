<?php

namespace App\Filament\Resources\GateLogs\Tables;

use App\Models\GateLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GateLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vehicle.vehicle_number')
                    ->label('Vehicle')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('driver.name')
                    ->label('Driver')
                    ->description(fn (GateLog $r) => $r->driver?->national_id.' · '.$r->driver?->phone)
                    ->searchable(),

                TextColumn::make('time_in')
                    ->label('Time In')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('time_out')
                    ->label('Time Out')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('— still on site —')
                    ->sortable(),

                TextColumn::make('duration')
                    ->label('On Site')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === GateLog::STATUS_IN ? 'success' : 'gray'),

                TextColumn::make('gatedInBy.name')
                    ->label('Gated In By')
                    ->toggleable(),

                TextColumn::make('gatedOutBy.name')
                    ->label('Gated Out By')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('time_in', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        GateLog::STATUS_IN => 'Currently on site',
                        GateLog::STATUS_OUT => 'Departed',
                    ]),
            ]);
    }
}
