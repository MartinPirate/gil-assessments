<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\GateLog;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gate in / gate out rules (Task 2).
 *
 * Both screens go through here so the "a vehicle can only be inside once"
 * invariant is enforced in one place rather than in each Livewire page.
 */
class GateService
{
    /**
     * Record a vehicle entering the site.
     *
     * @param  array<string, mixed>  $data
     */
    public function gateIn(array $data, int $userId): GateLog
    {
        return DB::transaction(function () use ($data, $userId) {
            // Locked so two gate officers cannot both admit the same vehicle:
            // the second call blocks, then sees the open record and fails.
            $vehicle = Vehicle::query()
                ->whereKey($data['vehicle_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->openLogFor($vehicle->getKey(), lock: true)) {
                throw ValidationException::withMessages([
                    'data.vehicle_id' => "Vehicle {$vehicle->vehicle_number} is already gated in.",
                ]);
            }

            $driver = Driver::find($data['driver_id'] ?? null);

            return GateLog::create([
                'vehicle_id' => $vehicle->getKey(),
                'vehicle_number' => $vehicle->vehicle_number,
                'driver_id' => $driver?->getKey(),
                // Snapshotted so the record still reads correctly if the
                // driver master row is edited later.
                'driver_name' => $data['driver_name'] ?? $driver?->name,
                'driver_national_id' => $data['driver_national_id'] ?? $driver?->national_id,
                'driver_phone' => $data['driver_phone'] ?? $driver?->phone,
                'time_in' => now(),                 // auto-captured
                'gated_in_by' => $userId,           // auto-captured
                'status' => GateLog::STATUS_IN,
                'gate_in_remarks' => $data['gate_in_remarks'] ?? null,
            ]);
        });
    }

    /**
     * Record a vehicle leaving the site.
     */
    public function gateOut(int $vehicleId, int $userId, ?string $remarks = null): GateLog
    {
        return DB::transaction(function () use ($vehicleId, $userId, $remarks) {
            $log = $this->openLogFor($vehicleId, lock: true);

            if (! $log) {
                throw ValidationException::withMessages([
                    'data.vehicle_id' => 'That vehicle is not currently gated in.',
                ]);
            }

            $log->update([
                'time_out' => now(),                // auto-captured
                'gated_out_by' => $userId,          // auto-captured
                'status' => GateLog::STATUS_OUT,
                'gate_out_remarks' => $remarks,
            ]);

            return $log->refresh();
        });
    }

    /**
     * The open gate-in record for a vehicle, optionally locked.
     */
    public function openLogFor(int $vehicleId, bool $lock = false): ?GateLog
    {
        $query = GateLog::query()
            ->where('vehicle_id', $vehicleId)
            ->where('status', GateLog::STATUS_IN)
            ->latest('time_in');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
