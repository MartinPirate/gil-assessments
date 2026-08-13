<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The barcode printed on the KRA ETR / eTIMS receipt.
 *
 * The other TIMS fields on the document are read-only: the control unit
 * assigns them when the invoice is transmitted. This one is the opposite - it
 * is captured by scanning the paper receipt the ETR prints, which is how a
 * clerk ties a physical receipt back to the document in the system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('etr_barcode', 64)->nullable();
            $table->dateTime('etr_scanned_at')->nullable();

            // Looked up by barcode when reconciling a stack of receipts.
            $table->index('etr_barcode');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['etr_barcode']);
            $table->dropColumn(['etr_barcode', 'etr_scanned_at']);
        });
    }
};
