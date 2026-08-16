<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sample document carries a bold "Customer Name" separate from the
 * business partner's "Name" — the name printed on this particular invoice,
 * which for a walk-in customer differs from the BP master record.
 *
 * Item/Service Type and the QR code are likewise document-level on the screen
 * rather than per line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('customer_display_name', 150)->nullable()->after('customer_name');
            $table->string('item_service_type', 16)->default('Item')->after('summary_type');
            $table->string('qr_code', 500)->nullable()->after('payment_order_run');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['customer_display_name', 'item_service_type', 'qr_code']);
        });
    }
};
