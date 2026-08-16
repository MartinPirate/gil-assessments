<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use App\Models\Driver;
use App\Models\GateLog;
use App\Models\Trip;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A driver, as an operations record rather than four form fields.
 *
 * The edit form answers "what is this person called". This answers what people
 * open a driver to ask: are they licensed, what have they driven, how much of
 * it did they finish, and when were they last through the gate.
 */
class ViewDriver extends ViewRecord
{
    protected static string $resource = DriverResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            ViewEntry::make('hero')
                ->hiddenLabel()
                ->view('filament.partials.driver-hero')
                ->columnSpanFull(),

            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make('Trips')
                    ->description('Every run assigned to this driver, most recent first.')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        ViewEntry::make('trips')
                            ->hiddenLabel()
                            ->view('filament.partials.driver-trips'),
                    ]),

                Section::make('Gate movements')
                    ->description('Vehicles brought in and taken out, and time on site.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        ViewEntry::make('gate')
                            ->hiddenLabel()
                            ->view('filament.partials.driver-gate-logs'),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        /** @var Driver $driver */
        $driver = $this->getRecord();

        $trips = Trip::where('driver_id', $driver->getKey());

        return [
            'trips' => (clone $trips)->count(),
            'completed' => (clone $trips)->where('status', Trip::STATUS_COMPLETED)->count(),
            'inTransit' => (clone $trips)->where('status', Trip::STATUS_IN_TRANSIT)->count(),
            'distance' => (clone $trips)
                ->where('status', Trip::STATUS_COMPLETED)
                ->join('routes', 'routes.id', '=', 'trips.route_id')
                ->sum('routes.distance_km'),
            'gateMovements' => GateLog::where('driver_id', $driver->getKey())->count(),
            'lastSeen' => GateLog::where('driver_id', $driver->getKey())->latest('time_in')->first()?->time_in,
            'onSite' => GateLog::where('driver_id', $driver->getKey())
                ->where('status', GateLog::STATUS_IN)
                ->latest('time_in')
                ->first(),
        ];
    }
}
