<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * The gate on every widget that reports money or documents.
 *
 * Selling and approving are the two jobs those figures mean anything to. A gate
 * officer has no access to sales at all, so the cards resolved to "Orders 0,
 * KES 0.00 raised" — three zeros that read as a broken dashboard rather than as
 * "not your department".
 *
 * One copy, because three widgets asking the same question in three places is
 * three places to forget when the answer changes.
 */
trait ShowsCommercialFigures
{
    public static function canView(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->canSell() || $user?->canApprove());
    }
}
