<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Resources\Trips\TripResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrip extends EditRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = TripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
