<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Resources\Items\ItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItem extends CreateRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = ItemResource::class;
}
