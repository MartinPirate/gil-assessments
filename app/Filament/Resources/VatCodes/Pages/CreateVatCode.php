<?php

namespace App\Filament\Resources\VatCodes\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Resources\VatCodes\VatCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVatCode extends CreateRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = VatCodeResource::class;
}
