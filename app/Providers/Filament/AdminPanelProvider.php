<?php

namespace App\Providers\Filament;

use App\Models\User;
use Alexkramse\FilamentOpenapiDocs\FilamentOpenApiDocsPlugin;
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;
use Blemli\FormSettings\FormSettingsPlugin;
use Filament\Changelog\ChangelogPlugin;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Gsferro\FilamentOdometerEasy\FilamentOdometerEasyPlugin;
use Hammadzafar05\FilamentMobilePreset\FilamentMobilePresetPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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
            ->colors([
                // Matches the SAP Business One document chrome.
                'primary' => Color::hex('#1f4e79'),
            ])
            ->maxContentWidth(Width::Full)
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
            ->widgets([
                AccountWidget::class,
                \App\Filament\Widgets\OperationsOverview::class,
            ])
            // Database notifications back the Notification Center below.
            ->databaseNotifications()
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
