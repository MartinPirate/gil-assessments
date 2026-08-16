<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Enums\UserRole;
use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Resources\Drivers\Concerns\ManagesDriverAccount;
use App\Filament\Resources\Drivers\DriverResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateDriver extends CreateRecord
{
    use AnnouncesTheRecord;
    use ManagesDriverAccount;

    protected static string $resource = DriverResource::class;

    /**
     * A driver and their login are created together, in one transaction.
     *
     * drivers.user_id is NOT NULL, so a driver saved without an account is not
     * a partially finished record — it is an impossible one. If the driver
     * insert fails, the orphaned user must go with it.
     */
    protected function handleRecordCreation(array $data): Model
    {
        [$driverData, $account] = $this->splitAccountFields($data);

        return DB::transaction(function () use ($driverData, $account): Model {
            $user = User::create([
                'name' => $driverData['name'],
                'email' => $account['email'],
                // Hashed by the model's 'password' cast.
                'password' => $account['password'],
                'is_active' => $driverData['is_active'] ?? true,
            ]);

            // The role is a Laratrust row rather than a column, so it is
            // attached once the account exists.
            $user->syncRoles([UserRole::Driver->value]);

            return static::getModel()::create([
                ...$driverData,
                'user_id' => $user->getKey(),
            ]);
        });
    }
}
