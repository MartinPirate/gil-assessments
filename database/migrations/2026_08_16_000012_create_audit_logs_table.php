<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who changed what, when, and from where.
 *
 * Approvals, payments and gate movements are all financially or physically
 * consequential, so "the system says so" is not good enough — every change has
 * to be attributable to a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Nullable: system and callback-driven changes have no logged-in user.
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('user_name', 150)->nullable();   // snapshot
            $table->string('user_role', 32)->nullable();

            $table->string('event', 32);                    // created | updated | deleted | restored
            $table->string('auditable_type', 191);          // model class
            $table->unsignedBigInteger('auditable_id');
            $table->string('auditable_label', 191)->nullable(); // e.g. "IN-00000002"

            // Only the attributes that actually changed, never the whole row.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('url', 500)->nullable();

            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
