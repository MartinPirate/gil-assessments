<?php

namespace App\Filament\Resources\Users\Concerns;

use App\Enums\UserRole;
use App\Models\AuditLog;

/**
 * Writes the chosen role to Laratrust once the user row exists.
 *
 * The role is not a column any more, so the form field does not dehydrate and
 * the assignment happens here instead. Both the create and the edit page need
 * it, so it lives in one place.
 */
trait AssignsRole
{
    protected function assignRole(): void
    {
        $role = UserRole::tryFrom((string) ($this->data['role'] ?? ''));

        // A disabled field submits nothing — an administrator editing their own
        // account cannot change their role, and must not lose it either.
        if ($role === null) {
            return;
        }

        $previous = $this->record->role();

        if ($previous === $role) {
            return;
        }

        $this->record->syncRoles([$role->value]);
        $this->record->load('roles.permissions');

        /*
         * Audited by hand, because the trail is driven by Eloquent's saved
         * events and this change touches a pivot rather than the users row.
         * Handing somebody the authority to approve payments is exactly the
         * kind of change the log exists for, so it must not go unrecorded
         * merely because of where it is stored.
         */
        $this->record->writeAuditLog(
            AuditLog::UPDATED,
            ['role' => $previous?->value],
            ['role' => $role->value],
        );
    }
}
