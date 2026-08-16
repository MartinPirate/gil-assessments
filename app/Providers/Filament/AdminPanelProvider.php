<?php

namespace App\Providers\Filament;

use App\Filament\Landing;
use App\Filament\Pages\ApiDocs;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\OperationsOverview;
use App\Http\Middleware\RestrictChangelogToAdministrators;
use App\Models\User;
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Blemli\FormSettings\FormSettingsPlugin;
use Filament\Changelog\ChangelogPlugin;
use Filament\Contracts\Plugin;
use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Enums\GlobalSearchPosition;
use Filament\Enums\UserMenuPosition;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Gsferro\FilamentOdometerEasy\FilamentOdometerEasyPlugin;
use Hammadzafar05\FilamentMobilePreset\FilamentMobilePresetPlugin;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use LaBoiteACode\DependencyGraph\DependencyGraphPlugin;
use LaBoiteACode\FilamentActivityTimeline\FilamentActivityTimelinePlugin;
use LaBoiteACode\FilamentLogsExplorer\FilamentLogsExplorerPlugin;
use lockscreen\FilamentLockscreen\Lockscreen;
use Prodstarter\FilamentNotificationCenter\FilamentNotificationCenterPlugin;
use Savanna\Theme\SavannaThemePlugin;
use Wallacemartinss\FilamentOnboarding\FilamentOnboardingPlugin;
use YousefAman\FilamentAutosave\AutosavePlugin;
use Zvizvi\FilamentColumnFilters\FilamentColumnFiltersPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->maxContentWidth(Width::Full)
            /*
             * Everything lives in the sidebar, and there is no top bar.
             *
             * Filament's default splits the chrome in two: brand, search,
             * notifications and the user menu sit in a topbar, and only the
             * navigation is in the sidebar. The reference does the opposite —
             * one column holds the brand, the search, the whole navigation and
             * the signed-in user, and the content area starts at the very top
             * of the page with nothing above the page title.
             */
            ->topbar(false)
            /*
             * No breadcrumb strip above the page title. The reference opens
             * each screen with the title itself, and in a panel this shallow a
             * breadcrumb only ever repeats the navigation item already
             * highlighted in the sidebar.
             */
            ->breadcrumbs(false)
            ->globalSearch(position: GlobalSearchPosition::Sidebar)
            ->userMenu(position: UserMenuPosition::Sidebar)
            // The sample document is a light desktop client. Honouring the OS
            // dark preference would render the whole thing dark and stop it
            // matching, so this panel is light only.
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            /*
             * Explicit group order, worked outward from the daily job: the
             * documents people raise, then the money against them, then the
             * yard, then the fleet. Reference data, tooling and reading matter
             * sit behind those because they are consulted, not worked.
             */
            ->navigationGroups([
                'Sales',
                'Payments',
                'Gate Operations',
                'Operations',
                'Administration',
                'Master Data',
                'Command Center',
                'Documentation',
                'Changelog',
            ])
            ->brandName('GIL Business Suite')
            /*
             * Signing in, and clicking the brand, land you on the screen you
             * work from. Evaluated per request, so it follows the signed-in
             * user rather than being fixed at boot.
             */
            ->homeUrl(fn (): string => Landing::urlFor(Auth::user()))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                // Registered here rather than by the plugin, so canAccess()
                // can keep it to administrators — see App\Filament\Pages\ApiDocs.
                ApiDocs::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            /*
             * No account widget: the signed-in user sits at the foot of the
             * sidebar now, so a "Welcome, <name>" card on the dashboard only
             * repeats it and pushes the real numbers down the page.
             */
            ->widgets([
                OperationsOverview::class,
            ])
            // Database notifications back the Notification Center below, and
            // sit with the user menu at the foot of the sidebar.
            ->databaseNotifications(position: DatabaseNotificationsPosition::Sidebar)
            ->plugins($this->plugins())
            /*
             * Column widths on the A/R Invoice grid are draggable, as they are
             * in the client. The panel does not load the application's own JS
             * bundle, so the behaviour is injected here.
             */
            // The tab icon: the document title bar's navy with the drill-arrow
            // orange, so the tab matches the panel rather than Filament's mark.
            ->favicon(asset('favicon-32.png'))
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.partials.icons'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): View => view('filament.partials.sap-grid-resize'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // The changelog reader is administrators-only, and the packaged
                // page has no access hook to hang that on.
                RestrictChangelogToAdministrators::class,
            ]);
    }

    /**
     * Panel plugins, grouped by what they are for.
     *
     * The system/diagnostic tools are hidden from everyone but an
     * administrator. They expose log contents, database relationships and
     * runnable commands, which the sales, gate and driver roles have no
     * business seeing — the same rule the rest of this panel follows.
     *
     * @return array<int, Plugin>
     */
    protected function plugins(): array
    {
        $isAdministrator = static function (): bool {
            $user = Filament::auth()->user();

            return $user instanceof User && $user->canAdminister();
        };

        return [
            /*
             * The theme goes first: it sets the panel's primary colour ramp and
             * sidebar width, and anything after it may deliberately override
             * those. The A/R Invoice document is unaffected — its SAP rules are
             * scoped to `.sap-page` and load after the theme's stylesheet.
             */
            SavannaThemePlugin::make(),

            /*
             * Lock the screen when a terminal is left alone.
             *
             * This matters more here than in an office system: the gate screens
             * run on a shared terminal at the yard entrance, and whoever is
             * signed in there can raise invoices and admit vehicles. Fifteen
             * minutes rather than the default thirty, because that terminal is
             * unattended between trucks.
             *
             * Repeated failures sign the session out rather than sitting on the
             * lock screen — on a shared machine an abandoned locked session is
             * the thing being attacked.
             */
            Lockscreen::make()
                ->enablePlugin()
                ->enableIdleTimeout(seconds: 15 * 60)
                ->enableRateLimit(limit: 5, decayMinutes: 5, forceLogout: true),

            // --- Working the screens ----------------------------------------
            AutosavePlugin::make(),
            FormSettingsPlugin::make(),
            FilamentColumnFiltersPlugin::make(),
            FilamentOdometerEasyPlugin::make(),
            FilamentMobilePresetPlugin::make(),

            // --- Orientation and comms --------------------------------------
            /*
             * Each journey is shown to the role it is written for, so a driver
             * is not walked through raising an invoice they cannot reach.
             *
             * These read the same UserRole capabilities the navigation and the
             * gates use, rather than naming roles again — a role's reach stays
             * described in one place. An unregistered condition hides what it
             * guards, so a typo here quietly shows nothing rather than showing
             * everything.
             */
            FilamentOnboardingPlugin::make()
                ->conditions([
                    'can-sell' => fn (User $user): bool => $user->canSell(),
                    'can-approve' => fn (User $user): bool => $user->canApprove(),
                    'can-operate-gate' => fn (User $user): bool => $user->canOperateGate(),
                    'is-driver' => fn (User $user): bool => $user->isDriver(),
                    'can-administer' => fn (User $user): bool => $user->canAdminister(),
                ], [
                    'can-sell' => 'Works on sales documents',
                    'can-approve' => 'Approves documents over the threshold',
                    'can-operate-gate' => 'Works the gate',
                    'is-driver' => 'Drives',
                    'can-administer' => 'Administers the system',
                ]),
            FilamentNotificationCenterPlugin::make(),
            FilamentActivityTimelinePlugin::make(),

            // --- Administrator-only system tools ----------------------------
            FilamentLogsExplorerPlugin::make()
                ->navigationGroup('Documentation')
                ->canAccessUsing($isAdministrator),

            DependencyGraphPlugin::make()
                ->navigationGroup('Documentation')
                ->canAccessUsing($isAdministrator),

            // Command Center reads its own gates from config/command-center.php;
            // they are defined in AppServiceProvider.
            CommandCenterPlugin::make(),

            /*
             * Administrators only, reading and writing alike. What changed in
             * the system is a maintenance record; a gate officer and a driver
             * have no more use for it than for the log viewer beside it.
             *
             * The nav condition hides the item; the route itself is closed by
             * RestrictChangelogToAdministrators, because the packaged page
             * offers no access hook.
             */
            ChangelogPlugin::make()
                ->navigationGroup('Changelog')
                ->registerNavigation($isAdministrator)
                ->canManage($isAdministrator),

        ];
    }
}
