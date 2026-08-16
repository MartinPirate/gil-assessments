<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Puts photographs on the fleet records.
 *
 * Reads whatever is in database/seeders/photos, keyed by the filename prefix
 * against the vehicle's type — `truck-01.jpg` goes on trucks, `van-02.jpg` on
 * vans. Drop your own files in and re-run; nothing here needs editing.
 *
 * A type with no photograph on hand is simply left without one, which the
 * fleet list already shows honestly. See NOTICE.md in that directory for where
 * the shipped images came from and under what licence.
 */
class VehiclePhotoSeeder extends Seeder
{
    public function run(): void
    {
        $byType = $this->photosByType();

        if ($byType === []) {
            return;
        }

        foreach (Vehicle::orderBy('id')->get() as $index => $vehicle) {
            if ($vehicle->hasPhoto()) {
                continue;
            }

            $type = Str::of((string) $vehicle->vehicle_type)->lower()->trim()->value();
            $available = $byType[$type] ?? [];

            if ($available === []) {
                continue;
            }

            // Rotated rather than always the first, so two vans in the list do
            // not look like the same van entered twice.
            $path = $available[$index % count($available)];

            $vehicle
                ->addMedia($path)
                ->preservingOriginal()
                ->usingFileName(Str::slug($vehicle->vehicle_number).'-'.basename($path))
                ->toMediaCollection(Vehicle::PHOTOS);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function photosByType(): array
    {
        $byType = [];

        foreach (glob(database_path('seeders/photos/*.jpg')) ?: [] as $path) {
            $type = Str::of(basename($path))->before('-')->lower()->value();
            $byType[$type][] = $path;
        }

        return $byType;
    }
}
