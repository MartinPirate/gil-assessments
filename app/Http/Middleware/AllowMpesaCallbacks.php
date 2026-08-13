<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The C2B callback URLs cannot be authenticated — Safaricom sends no
 * credentials — so the endpoint is restricted to Safaricom's published
 * callback IP ranges instead.
 *
 * Leaving MPESA_ALLOWED_IPS empty disables the check, which is what local
 * development and the sandbox need.
 */
class AllowMpesaCallbacks
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = array_filter(array_map(
            'trim',
            explode(',', (string) config('services.mpesa.allowed_ips'))
        ));

        if (empty($allowed)) {
            return $next($request);
        }

        if (! in_array($request->ip(), $allowed, true)) {
            Log::warning('Rejected M-Pesa callback from unexpected IP', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            // Deliberately terse: do not tell an unknown caller why.
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Rejected',
            ], 403);
        }

        return $next($request);
    }
}
