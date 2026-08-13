<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
