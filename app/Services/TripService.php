<?php

namespace App\Services;

use App\Enums\OrderStage;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Planning and running trips.
 */
class TripService
{
    public function __construct(
        protected DocumentNumberService $numbers,
        protected OrderLifecycleService $lifecycle,
    ) {}

    public const TRIP_SERIES = 'TRIP';

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \Throwable
     */
    public function schedule(array $data, ?int $userId = null): Trip
    {
        return DB::transaction(function () use ($data, $userId) {
            $route = Route::findOrFail($data['route_id']);
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);
            $driver = Driver::findOrFail($data['driver_id']);

            $scheduledAt = $data['scheduled_at'];

            // A vehicle or driver already committed to an open trip cannot be
            // double-booked; the gate would have no way to tell which journey
            // a movement belonged to.
            $this->assertAvailable('vehicle_id', $vehicle->getKey(), "Vehicle {$vehicle->vehicle_number}");
            $this->assertAvailable('driver_id', $driver->getKey(), $driver->name);

            $number = $this->numbers->next(self::TRIP_SERIES, self::TRIP_SERIES);

            return Trip::create([
                'reference' => 'TRP-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'route_id' => $route->getKey(),
                'vehicle_id' => $vehicle->getKey(),
                'driver_id' => $driver->getKey(),
                // Snapshotted so the trip still reads correctly later.
                'route_name' => $route->name,
                'vehicle_number' => $vehicle->vehicle_number,
                'driver_name' => $driver->name,
                'scheduled_at' => $scheduledAt,
                'status' => Trip::STATUS_SCHEDULED,
                'cargo_description' => $data['cargo_description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Refuse to commit a vehicle or driver that is already on an open trip.
     */
    protected function assertAvailable(string $column, int $id, string $label): void
    {
        $clash = Trip::query()->open()->where($column, $id)->lockForUpdate()->first();

        if ($clash) {
            throw ValidationException::withMessages([
                "data.{$column}" => "{$label} is already assigned to trip {$clash->reference} ({$clash->status}).",
            ]);
        }
    }

    public function depart(Trip $trip): Trip
    {
        return $this->transition($trip, Trip::STATUS_IN_TRANSIT, ['departed_at' => now()], [Trip::STATUS_SCHEDULED]);
    }

    public function arrive(Trip $trip): Trip
    {
        return $this->transition($trip, Trip::STATUS_COMPLETED, ['arrived_at' => now()], [Trip::STATUS_IN_TRANSIT]);
    }

    public function cancel(Trip $trip, ?string $reason = null): Trip
    {
        return $this->transition(
            $trip,
            Trip::STATUS_CANCELLED,
            ['notes' => trim(($trip->notes ? $trip->notes."\n" : '').'Cancelled: '.($reason ?: 'no reason given'))],
            [Trip::STATUS_SCHEDULED, Trip::STATUS_IN_TRANSIT],
        );
    }

    /**
     * All status changes go through here so the legal transitions live in one
     * place — a completed trip can never go back to scheduled.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $allowedFrom
     */
    protected function transition(Trip $trip, string $to, array $attributes, array $allowedFrom): Trip
    {
        return DB::transaction(function () use ($trip, $to, $attributes, $allowedFrom) {
            $locked = Trip::query()->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $allowedFrom, true)) {
                throw ValidationException::withMessages([
                    'status' => "A {$locked->status} trip cannot be moved to {$to}.",
                ]);
            }

            $locked->update($attributes + ['status' => $to]);

            $this->recordOrderStage($locked, $to);

            return $locked->refresh();
        });
    }

    /**
     * Carry a trip's movement onto the order it is carrying.
     *
     * Done here, in the one method every status change passes through, so
     * departing from the gate screen, the trip resource and the driver's phone
     * all record the same thing.
     *
     * A cancelled trip records nothing: the goods have not moved, and the order
     * itself is not cancelled just because this vehicle will not carry it.
     */
    protected function recordOrderStage(Trip $trip, string $status): void
    {
        if ($trip->invoice_id === null) {
            return;
        }

        $stage = match ($status) {
            Trip::STATUS_IN_TRANSIT => OrderStage::Dispatched,
            Trip::STATUS_COMPLETED => OrderStage::Delivered,
            default => null,
        };

        if ($stage === null) {
            return;
        }

        $invoice = $trip->invoice;

        if ($invoice === null) {
            return;
        }

        $this->lifecycle->record(
            $invoice,
            $stage,
            $stage === OrderStage::Dispatched ? $trip->departed_at : $trip->arrived_at,
            note: $stage === OrderStage::Dispatched
                ? "Trip {$trip->reference} departed on {$trip->route_name} ({$trip->vehicle_number})."
                : "Trip {$trip->reference} arrived. Driver: {$trip->driver_name}.",
            meta: ['trip_id' => $trip->getKey(), 'trip_reference' => $trip->reference],
        );
    }
}
