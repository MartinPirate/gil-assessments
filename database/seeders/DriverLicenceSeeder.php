<?php

namespace Database\Seeders;

use App\Models\Driver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;

/**
 * Puts a licence on file for the first two drivers.
 *
 * Enough for the screens to show both states — a driver whose licence is on
 * file and one whose is not, which is the state the gate cares about — without
 * pretending to be a real document. Each file says SPECIMEN across it in so
 * many words, because a plausible-looking copy of an identity document is not
 * something a demo seeder should be producing.
 */
class DriverLicenceSeeder extends Seeder
{
    public function run(): void
    {
        $drivers = Driver::orderBy('id')->take(2)->get();

        foreach ($drivers as $driver) {
            if ($driver->hasLicence()) {
                continue;
            }

            $pdf = Pdf::loadView('pdf.driver-licence', ['driver' => $driver])->setPaper('a5', 'landscape');

            $driver
                ->addMediaFromString($pdf->output())
                ->usingFileName('licence-'.$driver->national_id.'.pdf')
                ->toMediaCollection(Driver::LICENCE);
        }
    }
}
