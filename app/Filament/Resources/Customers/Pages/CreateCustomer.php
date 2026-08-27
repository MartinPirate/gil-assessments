<?php

namespace App\Filament\Pages\Resources\Customers\Pages;

use App\Filament\Concerns\AnnouncesTheRecord;
use App\Filament\Pages\Resources\Customers\CustomerResource;
use App\Services\CustomerCodeService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCustomer extends CreateRecord
{
    use AnnouncesTheRecord;

    protected static string $resource = CustomerResource::class;

    /**
     * The code is issued here rather than typed.
     *
     * Inside the transaction on purpose: CustomerCodeService locks the counter
     * row, and that lock is only worth having while the insert using the code
     * is still open. Issued outside it, two people pressing Create together
     * would be handed the same CC number.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $data['code'] = app(CustomerCodeService::class)->next();

            return static::getModel()::create($data);
        });
    }
}
