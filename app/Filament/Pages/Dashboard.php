<?php

namespace App\Filament\Pages;

use App\Filament\Landing;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * The panel root.
 *
 * This used to sit at /dashboard because the Launchpad plugin hard-coded its
 * own route to '/' and only one page can hold it. The launchpad has been
 * removed — a grid of tiles duplicating the sidebar was a page nobody needed —
 * so the dashboard is the landing page again, which is where signing in leaves
 * you.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?int $navigationSort = -1;

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    /**
     * Not everybody has one.
     *
     * Hidden rather than forbidden: the panel root still routes here, so a 403
     * would greet a gate officer who clicked the logo. mount() sends them on to
     * the screen they actually work from.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return Landing::hasDashboard(Auth::user());
    }

    /**
     * A filter bar across the top, which every widget below reads.
     *
     * Filament puts the filter state on `$filters` and re-renders the widgets
     * when it changes, so the widgets pull the range from the page rather than
     * each carrying its own date controls.
     */
    /**
     * Seed the filter state itself.
     *
     * A field default fills a form that is being *created*; the dashboard's
     * filter bar is bound to `$filters`, which starts empty, so the two date
     * boxes rendered blank and every widget silently read "no range". Setting
     * the state is what actually opens the page on the last 30 days.
     */
    public function mount(): void
    {
        if (! Landing::hasDashboard(Auth::user())) {
            $this->redirect(Landing::urlFor(Auth::user()));

            return;
        }

        $this->filters['from'] ??= now()->subDays(29)->startOfDay()->toDateString();
        $this->filters['until'] ??= now()->toDateString();
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    /*
                     * Two explicit dates rather than one range control, and
                     * filled in on arrival. A dashboard that opens on "all
                     * time" makes every figure on it meaningless until someone
                     * chooses a period, so it opens on the last 30 days and
                     * says so.
                     */
                    DatePicker::make('from')
                        ->label('Start date')
                        ->default(now()->subDays(29)->startOfDay())
                        ->maxDate(now())
                        ->native(false)
                        ->closeOnDateSelection(),

                    DatePicker::make('until')
                        ->label('End date')
                        ->default(now())
                        ->maxDate(now())
                        ->native(false)
                        ->closeOnDateSelection()
                        // A backwards range would silently return nothing.
                        ->afterOrEqual('from'),
                ])
                // Filament's filter schema is itself a multi-column grid, so
                // without this the whole bar sits in one narrow column and the
                // dates truncate mid-word.
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4]),
        ]);
    }

    /**
     * Four across on a wide screen — the stat row reads as one band rather
     * than wrapping three-and-two.
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'md' => 2, 'xl' => 4];
    }
}
