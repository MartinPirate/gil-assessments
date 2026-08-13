<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * The driver link lives on the drivers table, not on users, so it is
     * applied after the user row exists.
     */
    protected function afterSave(): void
    {
        $this->syncDriverLink();
    }

    protected function afterCreate(): void
    {
        $this->syncDriverLink();
    }

    protected function syncDriverLink(): void
    {
        $driverId = $this->data['driver_id'] ?? null;
        $user = $this->record;

        // Clear any previous link before setting the new one, so a driver
        // record never ends up attached to two logins.
        \App\Models\Driver::where('user_id', $user->getKey())->update(['user_id' => null]);

        if ($driverId && $user->role() === \App\Enums\UserRole::Driver) {
            \App\Models\Driver::whereKey($driverId)->update(['user_id' => $user->getKey()]);
        }
    }
}
