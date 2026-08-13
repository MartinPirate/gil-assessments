<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Routes, driver logins and a few trips to demonstrate the driver portal.
 */
class OperationsSeeder extends Seeder
{
    public function run(): void
    {
        $routes = [
            ['RT-NBO-MSA', 'Nairobi to Mombasa', 'Nairobi', 'Mombasa', 485.00, 9],
            ['RT-NBO-KSM', 'Nairobi to Kisumu', 'Nairobi', 'Kisumu', 340.00, 7],
            ['RT-NBO-NKR', 'Nairobi to Nakuru', 'Nairobi', 'Nakuru', 160.00, 3],
            ['RT-NBO-ELD', 'Nairobi to Eldoret', 'Nairobi', 'Eldoret', 310.00, 6],
            ['RT-CITY', 'Nairobi City Distribution', 'Nairobi CBD', 'Nairobi Metro', 45.00, 2],
        ];

        foreach ($routes as [$code, $name, $origin, $destination, $km, $hours]) {
            Route::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'origin' => $origin, 'destination' => $destination,
                 'distance_km' => $km, 'estimated_hours' => $hours, 'is_active' => true],
            );
        }

        // Give the first two drivers a login so the portal can be demonstrated.
        $logins = [
            ['23456789', 'driver@gil.test'],
            ['24567890', 'driver2@gil.test'],
        ];

        foreach ($logins as [$nationalId, $email]) {
            $driver = Driver::where('national_id', $nationalId)->first();

            if (! $driver) {
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $driver->name,
                    'password' => Hash::make('password'),
                    'role' => UserRole::Driver,
                    'is_active' => true,
                ],
            );

            $driver->update(['user_id' => $user->getKey()]);
        }

        // A couple of trips for the first driver: one to start, one running.
        $driver = Driver::where('national_id', '23456789')->first();
        $vehicles = Vehicle::orderBy('id')->take(2)->get();
        $routeModels = Route::orderBy('id')->take(2)->get();

        if ($driver && $vehicles->count() >= 2 && $routeModels->count() >= 2) {
            foreach ([
                ['TRP-000001', $routeModels[0], $vehicles[0], Trip::STATUS_SCHEDULED, now()->addHours(3), null],
                ['TRP-000002', $routeModels[1], $vehicles[1], Trip::STATUS_IN_TRANSIT, now()->subHours(4), now()->subHours(3)],
            ] as [$ref, $route, $vehicle, $status, $scheduled, $departed]) {
                Trip::updateOrCreate(
                    ['reference' => $ref],
                    [
                        'route_id' => $route->id,
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                        'route_name' => $route->name,
                        'vehicle_number' => $vehicle->vehicle_number,
                        'driver_name' => $driver->name,
                        'scheduled_at' => $scheduled,
                        'departed_at' => $departed,
                        'status' => $status,
                        'cargo_description' => '400 bales of Umi baking flour',
                    ],
                );
            }
        }
    }
}
