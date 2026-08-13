<?php

namespace App\Filament\Resources\MpesaTransactions\Pages;

use App\Filament\Resources\MpesaTransactions\MpesaTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListMpesaTransactions extends ListRecords
{
    protected static string $resource = MpesaTransactionResource::class;
}
