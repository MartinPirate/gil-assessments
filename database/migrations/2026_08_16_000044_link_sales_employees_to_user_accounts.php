<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a salesperson's login a master record to point at.
 *
 * Documents are attributed to a sales employee, but nothing connected that
 * employee to the account of the person signing in — so "show me my orders"
 * had nothing to match on and fell back to who typed the document, which for a
 * seeded register is one user for all of them.
 *
 * Nullable, like the driver link used to be: an administrator or a manager has
 * no sales employee record and does not need one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained();
        });

        // A plain unique index will not do: SQL Server treats two NULLs as
        // duplicates, so it would allow only one unlinked employee.
        DB::statement('
            CREATE UNIQUE INDEX sales_employees_user_id_unique
            ON sales_employees (user_id)
            WHERE user_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX sales_employees_user_id_unique ON sales_employees');

        Schema::table('sales_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
