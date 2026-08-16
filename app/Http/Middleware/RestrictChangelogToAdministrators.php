<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Changelog\Pages\ChangelogPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The changelog reader is for administrators.
 *
 * Hiding the sidebar item is not enough on its own — the page's route stays
 * open, and a URL somebody has seen once is a URL they can type. The packaged
 * page exposes no access hook of its own (its canAccess() defers to Filament
 * Shield, which this panel does not use), so the gate goes on the route.
 *
 * Matched on the page's own slug rather than a hard-coded path, so renaming it
 * in config cannot quietly open it up again.
 */
class RestrictChangelogToAdministrators
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('filament.admin.pages.'.ChangelogPage::getSlug())) {
            return $next($request);
        }

        $user = Auth::user();

        abort_unless($user instanceof User && $user->canAdminister(), 403);

        return $next($request);
    }
}
