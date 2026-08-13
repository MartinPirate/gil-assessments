<?php

namespace App\Filament\Resources\Trips\Schemas;

use App\Models\Invoice;
use App\Models\Trip;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reference')
                    ->required(),

                /*
                 * The order this trip carries. Optional: a repositioning run or
                 * a return leg is a real trip with nothing sold on it.
                 *
                 * When it is set, departing and arriving move the order's
                 * lifecycle to Dispatched and Delivered.
                 */
                Select::make('invoice_id')
                    ->label('Order')
                    ->helperText('Linking an order lets this trip advance it to dispatched and delivered.')
                    ->searchable()
                    ->preload()
                    ->getSearchResultsUsing(fn (string $search): array => Invoice::query()
                        ->posted()
                        ->where(fn ($query) => $query
                            ->where('doc_num', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%"))
                        ->limit(25)
                        ->get()
                        ->mapWithKeys(fn (Invoice $invoice) => [
                            $invoice->getKey() => "{$invoice->document_number} — {$invoice->customer_name}",
                        ])
                        ->all())
                    ->getOptionLabelUsing(function ($value): ?string {
                        $invoice = Invoice::find($value);

                        return $invoice
                            ? "{$invoice->document_number} — {$invoice->customer_name}"
                            : null;
                    }),

                Select::make('route_id')
                    ->relationship('route', 'name')
                    ->required(),
                Select::make('vehicle_id')
                    ->relationship('vehicle', 'vehicle_number')
                    ->required(),
                Select::make('driver_id')
                    ->relationship('driver', 'name')
                    ->required(),

                TextInput::make('route_name')
                    ->required(),
                TextInput::make('vehicle_number')
                    ->required(),
                TextInput::make('driver_name')
                    ->required(),

                Flatpickr::make('scheduled_at')
                    ->label('Scheduled at')
                    ->time()
                    ->required(),
                Flatpickr::make('departed_at')
                    ->label('Departed at')
                    ->time(),
                Flatpickr::make('arrived_at')
                    ->label('Arrived at')
                    ->time(),

                Select::make('status')
                    ->options(Trip::statusOptions())
                    ->required()
                    ->default(Trip::STATUS_SCHEDULED),

                TextInput::make('cargo_description')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
