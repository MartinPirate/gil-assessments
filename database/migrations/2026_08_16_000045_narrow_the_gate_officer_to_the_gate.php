<?php

use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;

/**
 * The gate officer stops being a trip planner.
 *
 * The role held manage-trips as well as operate-gate, which put Routes and
 * Trips in the sidebar of somebody whose job is the barrier. Planning is office
 * work; the officer admits vehicles, releases them, and reads the log.
 *
 * The matrix itself lives in UserRole::permissions(); this only brings an
 * already-provisioned database into line with it. AccessControl::sync() syncs
 * rather than appends, so the revoked permission is detached — and re-running
 * it is safe, which is why the seeder calls the same method.
 */
return new class extends Migration
{
    public function up(): void
    {
        AccessControl::sync();
    }

    public function down(): void
    {
        // Reverting the enum is what restores the old matrix; re-syncing from
        // whatever it says now is the only honest thing this can do.
        AccessControl::sync();
    }
};
