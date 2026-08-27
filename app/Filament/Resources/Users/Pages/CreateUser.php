<?php

namespace App\Filament\Pages\Resources\Users\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Pages\Resources\Users\Concerns\AssignsRole;
use App\Filament\Pages\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Driver records are not linked from here — every driver is created together
 * with its login on the Drivers screen, so attaching or releasing one from the
 * user side could only ever leave a driver without an account.
 */
class CreateUser extends CreateRecord
{
    use AnnouncesTheRecord;
    use AssignsRole;

    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $this->assignRole();
    }
}
