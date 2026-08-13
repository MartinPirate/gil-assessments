<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2 requires login timestamps and user sessions to be persisted.
 *
 * Laravel's own `sessions` table is a cache of the session payload and gets
 * garbage-collected; this is the durable audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('session_id', 191)->nullable();
            $table->dateTime('logged_in_at');
            $table->dateTime('logged_out_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'logged_in_at']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_sessions');
    }
};
