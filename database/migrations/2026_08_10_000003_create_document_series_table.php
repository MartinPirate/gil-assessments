<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counter table backing the auto-incremented "No." field.
 *
 * The number is handed out by locking this row inside the same transaction
 * that inserts the document, so two users pressing "Add & New" at the same
 * moment cannot be issued the same number. Reading MAX(doc_num)+1 would race.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40);   // e.g. AR_INVOICE
            $table->string('series', 8);           // e.g. IN
            $table->unsignedBigInteger('next_number');
            $table->timestamps();

            $table->unique(['document_type', 'series']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_series');
    }
};
