<?php

namespace App\Filament\Pages;

use Alexkramse\FilamentOpenapiDocs\Pages\OpenApiDocsPage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The API reference, for administrators only.
 *
 * The packaged plugin exposes no access hook — nothing to gate navigation or
 * entry with — so its page is registered through this subclass instead.
 * canAccess() is what Filament asks both when building the sidebar and when
 * serving the route, so one answer covers the menu item and the URL.
 *
 * It maps the application's request surface, including the M-Pesa callback
 * contract: a reference for whoever maintains the system, not for the people
 * using it.
 */
class ApiDocs extends OpenApiDocsPage
{
    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canAdminister();
    }
}
