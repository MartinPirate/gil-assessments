<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a driver a way to sign in.
 *
 * The driver master row is an operational record (who drove the truck); the
 * user row is a login. They are separate on purpose — most drivers never get an
 * account — so this is a nullable link rather than a merge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained();
        });

        // A plain unique index will not do here: SQL Server treats two NULLs as
        // duplicates, so it would allow only one driver without an account.
        // A filtered index enforces "one driver per user" only on real values.
        DB::statement('
            CREATE UNIQUE INDEX drivers_user_id_unique
            ON drivers (user_id)
            WHERE user_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX drivers_user_id_unique ON drivers');

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
