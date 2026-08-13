<?php

namespace App\Filament\Resources\SalesEmployees\Pages;

use App\Filament\Resources\SalesEmployees\SalesEmployeeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesEmployee extends EditRecord
{
    protected static string $resource = SalesEmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
