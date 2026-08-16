<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A journey takes nine and a half hours, not nine or ten.
 *
 * estimated_hours was an unsigned integer while the form offered a plain
 * numeric field, so typing 9.5 reached SQL Server as a string it refused to
 * convert — a 500 rather than a validation message. Widening the column is the
 * honest fix: the value really is fractional.
 *
 * decimal(6,2) covers a hundred-hour leg to the minute, which is well past
 * anything a road route needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->decimal('estimated_hours', 6, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Round on the way back rather than letting SQL Server truncate
        // silently — half an hour lost without a word is worse than a rounded
        // figure somebody can see.
        DB::statement('UPDATE routes SET estimated_hours = ROUND(estimated_hours, 0) WHERE estimated_hours IS NOT NULL');

        Schema::table('routes', function (Blueprint $table) {
            $table->unsignedInteger('estimated_hours')->nullable()->change();
        });
    }
};
