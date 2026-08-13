<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns exceptions into responses that suit the caller.
 *
 * Three audiences, three shapes:
 *  - Safaricom's callback parser, which only understands ResultCode;
 *  - other API clients, which want plain JSON;
 *  - people using the panel, who want a readable page and a reference number
 *    they can quote to support.
 *
 * Nothing here ever leaks a stack trace, SQL statement or connection detail to
 * the caller in production.
 */
class Handler
{
    /**
     * Exceptions that are ordinary control flow, not incidents worth alerting on.
     *
     * @var array<int, class-string>
     */
    public const NOT_REPORTED = [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
        TokenMismatchException::class,
    ];

    /**
     * A short id printed to the user and written to the log, so a support
     * report ("I saw error 9f3a2b") can be tied to a specific stack trace.
     */
    public static function reference(): string
    {
        return strtoupper(Str::random(8));
    }

    /**
     * Should this exception be logged as an incident?
     */
    public static function shouldReport(Throwable $e): bool
    {
        foreach (self::NOT_REPORTED as $class) {
            if ($e instanceof $class) {
                return false;
            }
        }

        // 4xx are the caller's problem; only 5xx are ours.
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() >= 500;
        }

        return true;
    }

    /**
     * Log with enough context to actually diagnose the failure.
     */
    public static function report(Throwable $e, Request $request, string $reference): void
    {
        Log::error($e->getMessage(), [
            'reference' => $reference,
            'exception' => $e::class,
            'file' => $e->getFile().':'.$e->getLine(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->getKey(),
            // Never log the body wholesale — it may carry credentials.
            'route' => $request->route()?->getName(),
        ]);
    }

    /**
     * The M-Pesa endpoints must always answer in Safaricom's shape, even when
     * something unrelated blows up — an HTML error page would be parsed as a
     * failure and the callback retried forever.
     */
    public static function isMpesaRoute(Request $request): bool
    {
        return $request->is('api/mpesa/*');
    }

    public static function statusFor(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        return match (true) {
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => 403,
            $e instanceof ModelNotFoundException => 404,
            $e instanceof ValidationException => 422,
            $e instanceof TokenMismatchException => 419,
            default => 500,
        };
    }

    /**
     * A message safe to show a stranger.
     */
    public static function safeMessage(Throwable $e, int $status): string
    {
        // Deliberately generic for 5xx: the real message may name tables,
        // columns or file paths.
        if ($status >= 500) {
            return 'Something went wrong on our side. The team has been notified.';
        }

        if ($e instanceof HttpExceptionInterface && filled($e->getMessage())) {
            return $e->getMessage();
        }

        return match ($status) {
            401 => 'You need to sign in to continue.',
            403 => 'You do not have permission to do that.',
            404 => 'We could not find what you were looking for.',
            419 => 'Your session expired. Please refresh and try again.',
            429 => 'Too many requests. Please slow down.',
            default => 'That request could not be completed.',
        };
    }
}
