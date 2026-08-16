<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Resources\Trips\TripResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrip extends CreateRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = TripResource::class;
}
