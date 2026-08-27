<?php

namespace App\Filament\Pages\Resources\Vehicles\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Pages\Resources\Vehicles\VehicleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = VehicleResource::class;
}
