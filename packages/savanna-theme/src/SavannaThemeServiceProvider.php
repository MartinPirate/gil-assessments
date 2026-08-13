<?php

namespace Savanna\Theme;

use Illuminate\Support\ServiceProvider;

/**
 * The theme is stylesheet-first: it publishes no views, migrations or config,
 * so this provider exists only to make the plugin resolvable and to give the
 * package somewhere to grow.
 */
class SavannaThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SavannaThemePlugin::class);
    }
}
