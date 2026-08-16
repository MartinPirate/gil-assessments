<?php

namespace App\Filament\Resources\Routes\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Resources\Routes\RouteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoute extends EditRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = RouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
