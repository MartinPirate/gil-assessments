<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The role dashboard, moved off the panel root.
 *
 * Launchpad hard-codes its own route path to '/' because being the landing
 * page is the whole point of it. Filament's stock Dashboard claims the same
 * path, so only one of the two could win — and with the dashboard losing,
 * every sidebar in the panel died on a missing
 * `filament.admin.pages.dashboard` route.
 *
 * Giving the dashboard an explicit path settles it: the launchpad keeps the
 * root, the dashboard keeps its route and its widgets, and both appear in the
 * navigation.
 */
class Dashboard extends BaseDashboard
{
    protected static string $routePath = 'dashboard';

    protected static ?int $navigationSort = -1;

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }
}
