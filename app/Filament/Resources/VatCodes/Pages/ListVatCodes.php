<?php

namespace App\Filament\Resources\VatCodes\Pages;

use App\Filament\Resources\VatCodes\VatCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVatCodes extends ListRecords
{
    protected static string $resource = VatCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
