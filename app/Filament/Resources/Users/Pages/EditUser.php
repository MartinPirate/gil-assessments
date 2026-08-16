<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Resources\Users\Concerns\AssignsRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Driver records are not linked from here — see CreateUser.
 */
class EditUser extends EditRecord
{
    use AnnouncesTheRecord;
    use AssignsRole;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->assignRole();
    }
}
