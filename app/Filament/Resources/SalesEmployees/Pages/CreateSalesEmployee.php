<?php

namespace App\Filament\Pages\Resources\SalesEmployees\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Pages\Resources\SalesEmployees\SalesEmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesEmployee extends CreateRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = SalesEmployeeResource::class;
}
