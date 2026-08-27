<?php

namespace App\Filament\Pages\Resources\Routes\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Pages\Resources\Routes\RouteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoute extends CreateRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = RouteResource::class;
}
