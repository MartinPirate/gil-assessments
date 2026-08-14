<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\GateLog;
use App\Models\Invoice;
use App\Models\Trip;
use App\Models\Vehicle;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A vehicle, as an operations record rather than four form fields.
 *
 * The edit form answers "what is this truck called". This answers the
 * questions people open a vehicle to ask: where is it, who drives it, what has
 * it carried, and where has it been.
 */
class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->vehicle_number;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            // Plate, illustration and the figures that describe the truck.
            ViewEntry::make('hero')
                ->hiddenLabel()
                ->view('filament.partials.vehicle-hero')
                ->columnSpanFull(),

            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make('Drivers')
                    ->description('Everyone who has taken this vehicle out, most recent first.')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        ViewEntry::make('drivers')
                            ->hiddenLabel()
                            ->view('filament.partials.vehicle-drivers'),
                    ]),

                Section::make('Routes run')
                    ->description('Where this vehicle has actually been, and how often.')
                    ->icon('heroicon-o-map')
                    ->schema([
                        ViewEntry::make('routes')
                            ->hiddenLabel()
                            ->view('filament.partials.vehicle-routes'),
                    ]),
            ])->columnSpanFull(),

            Section::make('Orders carried')
                ->description('Documents this vehicle has moved, newest first.')
                ->icon('heroicon-o-document-text')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('carriedOrders')
                        ->hiddenLabel()
                        ->state(fn (Vehicle $record): array => $record->trips()
                            ->whereNotNull('invoice_id')
                            ->with('invoice')
                            ->get()
                            ->map(fn (Trip $trip): array => [
                                'document' => $trip->invoice?->document_number ?? '—',
                                'customer' => $trip->invoice?->customer_name ?? '—',
                                'total' => number_format((float) ($trip->invoice?->document_total ?? 0), 2),
                                'trip' => $trip->reference,
                                'status' => $trip->status,
                            ])
                            ->all())
                        ->table([
                            TableColumn::make('Document'),
                            TableColumn::make('Customer'),
                            TableColumn::make('Value')->alignEnd(),
                            TableColumn::make('Trip'),
                            TableColumn::make('Status'),
                        ])
                        ->schema([
                            TextEntry::make('document')->hiddenLabel()->weight('semibold'),
                            TextEntry::make('customer')->hiddenLabel(),
                            TextEntry::make('total')->hiddenLabel()->prefix('KES '),
                            TextEntry::make('trip')->hiddenLabel(),
                            TextEntry::make('status')->hiddenLabel()->badge(),
                        ])
                        ->placeholder('This vehicle has not carried a sold order yet.'),
                ]),

            Section::make('Gate history')
                ->description('Every time this vehicle entered and left the yard.')
                ->icon('heroicon-o-queue-list')
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    ViewEntry::make('gate')
                        ->hiddenLabel()
                        ->view('filament.partials.vehicle-gate-history'),
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->getRecord();

        $open = $vehicle->gateLogs()->where('status', GateLog::STATUS_IN)->latest('time_in')->first();

        return [
            'trips' => $vehicle->trips()->count(),
            'completed' => $vehicle->trips()->where('status', Trip::STATUS_COMPLETED)->count(),
            'distance' => $vehicle->distance_covered,
            'orders' => $vehicle->trips()->whereNotNull('invoice_id')->count(),
            'value' => (float) Invoice::query()
                ->whereIn('id', $vehicle->trips()->whereNotNull('invoice_id')->pluck('invoice_id'))
                ->sum('document_total'),
            'onSite' => $open !== null,
            'since' => $open?->time_in,
            'lastSeen' => $vehicle->gateLogs()->latest('time_in')->first()?->time_in,
        ];
    }
}
