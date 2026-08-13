<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A small fixed role set rather than a full ACL package: the app has four
 * clearly separated jobs (sales entry, approval, gate, administration) and a
 * permissions table would be machinery without a use for it here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('sales')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            // The ceiling this user may approve up to; null = unlimited.
            $table->decimal('approval_limit', 18, 3)->nullable()->after('is_active');

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'approval_limit']);
        });
    }
};
