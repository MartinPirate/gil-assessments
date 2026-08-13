<?php

namespace App\Filament\Resources\GateLogs\Pages;

use App\Filament\Resources\GateLogs\GateLogResource;
use Filament\Resources\Pages\ListRecords;

class ListGateLogs extends ListRecords
{
    protected static string $resource = GateLogResource::class;
}
