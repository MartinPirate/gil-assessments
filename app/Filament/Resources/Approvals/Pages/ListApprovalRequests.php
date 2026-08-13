<?php

namespace App\Filament\Resources\Approvals\Pages;

use App\Filament\Resources\Approvals\ApprovalRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListApprovalRequests extends ListRecords
{
    protected static string $resource = ApprovalRequestResource::class;
}
