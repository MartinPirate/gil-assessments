<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Resources\Drivers\Concerns\ManagesDriverAccount;
use App\Filament\Resources\Drivers\DriverResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditDriver extends EditRecord
{
    use AnnouncesTheRecord;
    use ManagesDriverAccount;

    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * The driver row and its login are saved together.
     *
     * A blank password means "leave it alone" — the form drops the field
     * entirely in that case, so there is nothing here to guard against.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        [$driverData, $account] = $this->splitAccountFields($data);

        return DB::transaction(function () use ($record, $driverData, $account): Model {
            $record->update($driverData);

            if ($account !== []) {
                // Read fresh: renaming the driver mirrors onto the user row
                // behind Eloquent's back, so a relation loaded earlier in this
                // request would be describing the name it used to have.
                $record->user()->first()?->update($account);
            }

            return $record;
        });
    }
}
