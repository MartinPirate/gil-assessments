<?php

namespace App\Listeners;

use App\Models\LoginSession;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

/**
 * Closes the matching login session so the audit trail shows a session
 * length rather than an open-ended login.
 */
class RecordLogout
{
    public function __construct(protected Request $request) {}

    public function handle(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        // Resolved to an id first: SQL Server does not accept ORDER BY on an
        // UPDATE, so "latest open session" has to be selected before writing.
        $session = LoginSession::query()
            ->where('user_id', $event->user->getAuthIdentifier())
            ->whereNull('logged_out_at')   // IS NULL, not = NULL
            ->latest('logged_in_at')
            ->first();

        $session?->update(['logged_out_at' => now()]);
    }
}
