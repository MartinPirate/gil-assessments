<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Narrows a resource to the documents the signed-in person raised.
 *
 * A salesperson sees their own work. Administrators and anyone who may approve
 * see everything, because both jobs are about other people's documents — an
 * approver who could only see their own would have an empty queue, and an
 * administrator who could not see the register could not administer it.
 *
 * Applied in getEloquentQuery(), which every list, view and count on the
 * resource runs through, rather than on the table alone — a filter on the
 * table would leave the record page reachable by typing its id in the URL.
 */
trait ScopesToOwnWork
{
    protected static function seesEverything(?User $user): bool
    {
        return (bool) ($user?->canAdminister() || $user?->canApprove());
    }

    /**
     * Whether the query should be narrowed for whoever is signed in.
     */
    protected static function shouldScopeToOwn(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ! static::seesEverything($user);
    }

    /**
     * "Mine" means attributed to me, not typed by me.
     *
     * A document names the sales employee it belongs to, and that is what the
     * register shows and what a salesperson recognises as theirs. Filtering on
     * created_by instead matched whoever keyed it in — which on a seeded or
     * imported register is one account for every document, so the filter let
     * everything through.
     *
     * Someone with no employee record falls back to what they raised, so a new
     * account is never shown somebody else's work.
     */
    protected static function scopeInvoicesToOwn(Builder $query): Builder
    {
        if (! static::shouldScopeToOwn()) {
            return $query;
        }

        /** @var User $user */
        $user = Auth::user();
        $employeeId = $user->salesEmployeeId();

        return $employeeId === null
            ? $query->where('created_by', $user->getKey())
            : $query->where('sales_employee_id', $employeeId);
    }
}
