<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery routes and the trips that run them.
 *
 * A gate movement on its own says a vehicle left; a trip says where it was
 * going, who was driving and whether it arrived. This is what a driver signs in
 * to see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();          // RT-NBO-MSA
            $table->string('name', 150);
            $table->string('origin', 150);
            $table->string('destination', 150);
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->unsignedInteger('estimated_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();     // TRP-000001

            $table->foreignId('route_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('driver_id')->constrained();

            // Snapshotted so a trip still reads correctly if masters change.
            $table->string('route_name', 150);
            $table->string('vehicle_number', 32);
            $table->string('driver_name', 150);

            $table->dateTime('scheduled_at');
            $table->dateTime('departed_at')->nullable();
            $table->dateTime('arrived_at')->nullable();

            // Scheduled | In Transit | Completed | Cancelled
            $table->string('status', 20)->default('Scheduled');
            $table->string('cargo_description', 500)->nullable();
            $table->string('notes', 1000)->nullable();

            // No cascade: SQL Server rejects multiple cascade paths to users.
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['vehicle_id', 'status']);
            $table->index('scheduled_at');
        });

        // A gate movement can belong to a trip, so the driver's screen can show
        // "left the yard at 08:12" against the right journey.
        Schema::table('gate_logs', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable()->after('driver_id')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('gate_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trip_id');
        });

        Schema::dropIfExists('trips');
        Schema::dropIfExists('routes');
    }
};
