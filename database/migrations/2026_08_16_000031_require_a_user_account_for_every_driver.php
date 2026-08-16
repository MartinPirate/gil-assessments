<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Every driver is a user.
 *
 * The link used to be optional, on the reasoning that most drivers never sign
 * in. That is no longer the case: a driver record without an account is now
 * considered incomplete, so the column becomes NOT NULL and each existing
 * driver without a login is given one.
 *
 * The backfilled accounts use the same demo password as every other seeded
 * account. That is a deliberate choice for this assessment build, not a
 * pattern to copy into production, where these would be issued as invitations.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $drivers = DB::table('drivers')
            ->whereNull('user_id')
            ->orderBy('id')
            ->get(['id', 'name']);

        foreach ($drivers as $driver) {
            $userId = DB::table('users')->insertGetId([
                'name' => $driver->name,
                'email' => $this->nextAvailableEmail(),
                'password' => Hash::make('password'),
                'role' => 'driver',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('drivers')->where('id', $driver->id)->update(['user_id' => $userId]);
        }

        // The filtered index and the foreign key both sit on this column, and
        // SQL Server refuses to alter a column anything else depends on — so
        // they come off, the column changes, and they go back.
        DB::statement('DROP INDEX drivers_user_id_unique ON drivers');

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE drivers ALTER COLUMN user_id BIGINT NOT NULL');

        Schema::table('drivers', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users');
            // A plain unique index is enough now: with no NULLs left there is
            // nothing for SQL Server's "two NULLs are duplicates" rule to bite.
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE drivers ALTER COLUMN user_id BIGINT NULL');

        Schema::table('drivers', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users');
        });

        DB::statement('
            CREATE UNIQUE INDEX drivers_user_id_unique
            ON drivers (user_id)
            WHERE user_id IS NOT NULL
        ');

        // The accounts created on the way up are left in place: rolling back
        // the constraint is not a reason to delete logins people may be using.
    }

    /**
     * The next driver login not already taken — driver@, then driver2@ upward.
     */
    private function nextAvailableEmail(): string
    {
        $suffix = 1;

        do {
            $email = 'driver'.($suffix > 1 ? $suffix : '').'@gil.test';
            $suffix++;
        } while (DB::table('users')->where('email', $email)->exists());

        return $email;
    }
};
