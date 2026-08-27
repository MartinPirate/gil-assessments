<?php

namespace App\Filament\Pages\Resources\Items\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Pages\Resources\Items\ItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItem extends EditRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
