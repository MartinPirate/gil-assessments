<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;

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
    use HasFiltersForm;

    protected static string $routePath = 'dashboard';

    protected static ?int $navigationSort = -1;

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    /**
     * A filter bar across the top, which every widget below reads.
     *
     * Filament puts the filter state on `$filters` and re-renders the widgets
     * when it changes, so the widgets pull the range from the page rather than
     * each carrying its own date controls.
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    DateRangePicker::make('period')
                        ->label('Period')
                        ->placeholder('All time')
                        ->maxDate(now()),
                ])
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4]),
        ]);
    }

    /**
     * Four across on a wide screen — the stat row reads as one band rather
     * than wrapping three-and-two.
     */
    public function getColumns(): int | array
    {
        return ['default' => 1, 'md' => 2, 'xl' => 4];
    }
}
