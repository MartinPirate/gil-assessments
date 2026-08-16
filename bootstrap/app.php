<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'mpesa.callback' => \App\Http\Middleware\AllowMpesaCallbacks::class,
        ]);

        /*
         * Behind Railway's edge, every request arrives over plain HTTP on an
         * internal address. Without this, Laravel builds http:// links on an
         * https:// page and the browser blocks its own assets — the panel
         * loads unstyled and Livewire never reaches the server.
         *
         * The proxy is the platform's own and is not addressable directly,
         * so there is no list of addresses to pin to.
         */
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(\App\Exceptions\Handler::NOT_REPORTED);

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            $handler = \App\Exceptions\Handler::class;

            // Let Laravel handle validation and auth redirects normally.
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            $status = $handler::statusFor($e);
            $reference = $handler::reference();

            if ($handler::shouldReport($e)) {
                $handler::report($e, $request, $reference);
            }

            // Safaricom's parser only understands ResultCode; an HTML error
            // page would be read as a failure and retried indefinitely.
            if ($handler::isMpesaRoute($request)) {
                return response()->json([
                    'ResultCode' => 1,
                    'ResultDesc' => $handler::safeMessage($e, $status),
                    'success' => false,
                    'reference' => $reference,
                ], $status);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $handler::safeMessage($e, $status),
                    'reference' => $reference,
                ], $status);
            }

            // Only take over full page renders for the statuses we style;
            // anything else keeps Laravel's default behaviour.
            if (in_array($status, [403, 404, 419, 500, 503], true) && ! config('app.debug')) {
                return response()->view('errors.custom', [
                    'status' => $status,
                    'message' => $handler::safeMessage($e, $status),
                    'reference' => $reference,
                ], $status);
            }

            return null;
        });
    })->create();
