<?php

namespace App\Listeners;

use App\Models\LoginSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Task 2a: capture login timestamps and persist the user's session.
 */
class RecordLoginSession
{
    public function __construct(protected Request $request) {}

    public function handle(Login $event): void
    {
        LoginSession::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'session_id' => $this->request->hasSession() ? $this->request->session()->getId() : null,
            'logged_in_at' => now(),
            'ip_address' => $this->request->ip(),
            // The column is 500 chars; some agents are longer than that.
            'user_agent' => substr((string) $this->request->userAgent(), 0, 500),
        ]);
    }
}
