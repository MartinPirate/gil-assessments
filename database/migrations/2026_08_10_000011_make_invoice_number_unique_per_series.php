<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document numbers are issued per series, not globally.
 *
 * DocumentNumberService keeps a counter per (document_type, series), so IN-5
 * and CR-5 are two different documents and both must be storable. The original
 * global unique index on doc_num made the second one impossible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_doc_num_unique');
            $table->unique(['series', 'doc_num']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['series', 'doc_num']);
            $table->unique('doc_num');
        });
    }
};
