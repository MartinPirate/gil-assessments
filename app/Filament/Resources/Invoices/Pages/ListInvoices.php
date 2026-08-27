<?php

namespace App\Filament\Pages\Resources\Invoices\Pages;

use App\Filament\Pages\Resources\Invoices\InvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;
}
