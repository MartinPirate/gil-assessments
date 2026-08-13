<?php

namespace App\Providers\Filament;

use App\Models\User;
use Alexkramse\FilamentOpenapiDocs\FilamentOpenApiDocsPlugin;
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Blemli\FormSettings\FormSettingsPlugin;
use Filament\Changelog\ChangelogPlugin;
use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Enums\GlobalSearchPosition;
use Filament\Enums\UserMenuPosition;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\Widgets\FilamentInfoWidget;
use Gsferro\FilamentOdometerEasy\FilamentOdometerEasyPlugin;
use Hammadzafar05\FilamentMobilePreset\FilamentMobilePresetPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Savanna\Theme\SavannaThemePlugin;
use LaBoiteACode\DependencyGraph\DependencyGraphPlugin;
use LaBoiteACode\FilamentActivityTimeline\FilamentActivityTimelinePlugin;
use LaBoiteACode\FilamentLogsExplorer\FilamentLogsExplorerPlugin;
use Prodstarter\FilamentNotificationCenter\FilamentNotificationCenterPlugin;
use UniFileManager\FilamentFileManager\FilamentFileManagerPlugin;
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
            ->brandName('GIL Business Suite')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            /*
             * No account widget: the signed-in user sits at the foot of the
             * sidebar now, so a "Welcome, <name>" card on the dashboard only
             * repeats it and pushes the real numbers down the page.
             */
            ->widgets([
                \App\Filament\Widgets\OperationsOverview::class,
            ])
            // Database notifications back the Notification Center below, and
            // sit with the user menu at the foot of the sidebar.
            ->databaseNotifications(position: DatabaseNotificationsPosition::Sidebar)
            ->plugins($this->plugins())
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
     * @return array<int, \Filament\Contracts\Plugin>
     */
    protected function plugins(): array
    {
        $isAdministrator = static function (): bool {
            $user = Filament::auth()->user();

            return $user instanceof User && $user->role()->canAdminister();
        };

        return [
            /*
             * The theme goes first: it sets the panel's primary colour ramp and
             * sidebar width, and anything after it may deliberately override
             * those. The A/R Invoice document is unaffected — its SAP rules are
             * scoped to `.sap-page` and load after the theme's stylesheet.
             */
            SavannaThemePlugin::make(),

            // --- Documents and data -----------------------------------------
            // Receipts and invoice attachments.
            FilamentFileManagerPlugin::make(),

            // --- Working the screens ----------------------------------------
            AutosavePlugin::make(),
            FormSettingsPlugin::make(),
            FilamentColumnFiltersPlugin::make(),
            FilamentOdometerEasyPlugin::make(),
            FilamentMobilePresetPlugin::make(),

            // --- Orientation and comms --------------------------------------
            LaunchpadPlugin::make(),
            FilamentOnboardingPlugin::make(),
            FilamentNotificationCenterPlugin::make(),
            FilamentActivityTimelinePlugin::make(),

            // --- Administrator-only system tools ----------------------------
            FilamentLogsExplorerPlugin::make()
                ->canAccessUsing($isAdministrator),

            DependencyGraphPlugin::make()
                ->canAccessUsing($isAdministrator),

            // Command Center reads its own gates from config/command-center.php;
            // they are defined in AppServiceProvider.
            CommandCenterPlugin::make(),

            /*
             * The changelog reader stays open to every signed-in user - telling
             * people what changed is the point of it. Only an administrator may
             * write entries.
             */
            ChangelogPlugin::make()
                ->canManage($isAdministrator),

            /*
             * The API reference documents the M-Pesa C2B endpoints. It maps the
             * request surface of the application, so it is kept off production
             * and out of every navigation but the administrator's.
             */
            FilamentOpenApiDocsPlugin::make()
                ->enabledInProduction(false)
                ->navigationGroup('Administration'),
        ];
    }
}
