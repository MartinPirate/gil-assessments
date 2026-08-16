<?php

namespace App\Filament\Resources\VatCodes\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Resources\VatCodes\VatCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVatCode extends EditRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = VatCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
