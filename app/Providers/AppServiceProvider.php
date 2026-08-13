<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Timeline\OrderStageSource;
use Illuminate\Cache\RateLimiting\Limit;
use LaBoiteACode\FilamentActivityTimeline\ActivityTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureGates();

        // The order lifecycle, available to any timeline as ->source('order').
        ActivityTimeline::registerSource('order', fn (): OrderStageSource => new OrderStageSource());
    }

    /**
     * Abilities the installed Filament plugins ask about.
     *
     * These are deliberately written against the same UserRole capabilities the
     * rest of the panel uses, so a role's reach is described in one place
     * rather than drifting between the app and its plugins.
     */
    protected function configureGates(): void
    {
        // Receipt and invoice attachments. Scoped by config to a private
        // "file-manager" root, not the whole disk, so the people who raise the
        // documents can also manage the files that back them.
        Gate::define('manageFileManager', fn (User $user): bool => $user->role()->canSell());

        /*
         * Command Center runs real artisan commands from the browser. That is
         * deploy-level authority, so all three of its abilities stay with the
         * administrator — an undefined gate would deny anyway, but saying so
         * explicitly keeps the decision visible.
         */
        Gate::define('command-center:access', fn (User $user): bool => $user->role()->canAdminister());
        Gate::define('command-center:prune-history', fn (User $user): bool => $user->role()->canAdminister());
        Gate::define('command-center:manage-commands', fn (User $user): bool => $user->role()->canAdminister());
    }

    /**
     * Bound per short-code + phone rather than per IP: all of Safaricom's
     * callbacks arrive from a small pool of addresses, so an IP-keyed limiter
     * would throttle every merchant at once.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('mpesa', function (Request $request) {
            $key = implode('|', [
                (string) $request->input('BusinessShortCode', 'unknown'),
                (string) $request->input('MSISDN', $request->ip()),
            ]);

            return Limit::perMinute(120)->by($key);
        });
    }
}
