<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An approval request is normally raised by whoever saved the invoice, but a
 * request opened by the repair command has no requester — the document was
 * already pending and nobody asked for anything.
 *
 * Recording a fabricated user there would be worse than recording none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_by')->nullable(false)->change();
        });
    }
};
