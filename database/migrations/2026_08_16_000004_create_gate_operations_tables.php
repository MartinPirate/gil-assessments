<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle gate operations (Task 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_number', 32)->unique();   // KDA 123A
            $table->string('make', 80)->nullable();
            $table->string('vehicle_type', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('national_id', 32)->unique();      // "Driver ID"
            $table->string('phone', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('gate_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained();
            $table->string('vehicle_number', 32);
            $table->foreignId('driver_id')->nullable()->constrained();
            // Driver details are snapshotted at gate-in: the person who drove
            // in must remain on the record even if the driver master changes.
            $table->string('driver_name', 150);
            $table->string('driver_national_id', 32);
            $table->string('driver_phone', 32);

            $table->dateTime('time_in');
            $table->dateTime('time_out')->nullable();
            // No cascade on either FK: SQL Server rejects multiple cascade
            // paths into the same table, and users are never hard-deleted.
            $table->foreignId('gated_in_by')->constrained('users');
            $table->foreignId('gated_out_by')->nullable()->constrained('users');
            $table->string('status', 12)->default('IN');      // IN | OUT
            $table->string('gate_in_remarks', 500)->nullable();
            $table->string('gate_out_remarks', 500)->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_logs');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('vehicles');
    }
};
