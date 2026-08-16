<?php

namespace App\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\MyTrips;
use App\Filament\Pages\VehicleGateIn;
use App\Models\User;

/**
 * Where a person starts when they sign in, and whether a dashboard is one of
 * the things they have.
 *
 * The dashboard reports on money and documents. A gate officer has neither: the
 * page resolved to a filter bar over one card, and the three sales figures it
 * used to carry read "0" — which says "broken" rather than "not your
 * department". They start at the barrier instead, and a driver starts at their
 * own trips.
 *
 * Both the panel's home URL and the page itself ask this class, so signing in
 * and clicking the logo cannot disagree about where a person belongs.
 */
class Landing
{
    /**
     * A dashboard is worth having if the figures on it are yours to read.
     */
    public static function hasDashboard(?User $user): bool
    {
        return (bool) ($user?->canSell()
            || $user?->canApprove()
            || $user?->canViewPayments()
            || $user?->canAdminister());
    }

    public static function urlFor(?User $user): string
    {
        if (static::hasDashboard($user)) {
            return Dashboard::getUrl();
        }

        if ($user?->isDriver() && $user->driverId()) {
            return MyTrips::getUrl();
        }

        if ($user?->canOperateGate()) {
            return VehicleGateIn::getUrl();
        }

        // Nothing else fits — the dashboard is at least a page that renders,
        // and an account with no capabilities at all is a provisioning
        // mistake to be seen rather than a redirect loop.
        return Dashboard::getUrl();
    }
}
