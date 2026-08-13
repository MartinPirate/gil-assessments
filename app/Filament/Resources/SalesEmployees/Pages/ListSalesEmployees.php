<?php

namespace App\Filament\Resources\SalesEmployees\Pages;

use App\Filament\Resources\SalesEmployees\SalesEmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalesEmployees extends ListRecords
{
    protected static string $resource = SalesEmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
