<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freight, itemised.
 *
 * invoices.freight was a single figure with no way to say what it was for and
 * — more to the point — no way to tax it. Tax is worked out from the lines and
 * freight was added afterwards, so a delivery charge contributed nothing to
 * VAT. In Kenya transport supplied with taxable goods is standard rated, so
 * that figure was wrong the moment freight stopped being zero.
 *
 * Each charge now carries its own VAT code, because they genuinely differ:
 * delivery is standard rated, insurance usually is not. A single blanket rate
 * on the total would be wrong in one direction or the other.
 *
 * invoices.freight stays as the sum, so every existing reader of that column
 * keeps working and the figure is still on the document where the sample has
 * it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_freight_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_num');
            $table->string('description', 150);
            $table->decimal('amount', 18, 3)->default(0);

            $table->foreignId('vat_code_id')->nullable()->constrained('vat_codes');
            // Snapshotted for the same reason the lines snapshot theirs: rates
            // change by legislation and a document already issued has to keep
            // saying what was charged.
            $table->decimal('vat_rate', 8, 3)->default(0);
            $table->decimal('vat_amount', 18, 3)->default(0);

            $table->string('remarks', 255)->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'line_num']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_freight_charges');
    }
};
