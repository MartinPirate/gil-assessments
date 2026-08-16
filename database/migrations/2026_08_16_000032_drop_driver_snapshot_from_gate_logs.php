<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A gate log names its driver and its vehicle by id alone.
 *
 * The driver's name, national ID and phone, and the vehicle's number, used to
 * be copied onto every row so the log would still read correctly after the
 * master records changed. That trade is dropped in favour of a single place to
 * correct: the log now reads through to drivers and vehicles, and an edit is
 * reflected everywhere at once.
 *
 * Two things replace what the snapshot was protecting:
 *   - driver_id becomes NOT NULL, so a log can never be about nobody.
 *   - both foreign keys stay NO ACTION, so a driver or vehicle that has been
 *     through the gate cannot be deleted out from under its history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_logs', function (Blueprint $table) {
            $table->dropColumn(['driver_name', 'driver_national_id', 'driver_phone', 'vehicle_number']);
        });

        // SQL Server will not alter a column a foreign key depends on.
        Schema::table('gate_logs', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
        });

        DB::statement('ALTER TABLE gate_logs ALTER COLUMN driver_id BIGINT NOT NULL');

        Schema::table('gate_logs', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('drivers');
        });
    }

    public function down(): void
    {
        Schema::table('gate_logs', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
        });

        DB::statement('ALTER TABLE gate_logs ALTER COLUMN driver_id BIGINT NULL');

        Schema::table('gate_logs', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('drivers');

            $table->string('driver_name', 150)->nullable();
            $table->string('driver_national_id', 32)->nullable();
            $table->string('driver_phone', 32)->nullable();
            $table->string('vehicle_number', 32)->nullable();
        });

        // Refilled from the master records as they stand now. What the columns
        // held at the time of each visit is not recoverable — which is the
        // whole point of having dropped them.
        DB::statement('
            UPDATE g
            SET g.driver_name = d.name,
                g.driver_national_id = d.national_id,
                g.driver_phone = d.phone,
                g.vehicle_number = v.vehicle_number
            FROM gate_logs g
            INNER JOIN drivers d ON d.id = g.driver_id
            INNER JOIN vehicles v ON v.id = g.vehicle_id
        ');
    }
};
