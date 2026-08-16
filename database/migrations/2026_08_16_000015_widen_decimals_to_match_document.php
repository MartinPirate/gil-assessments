<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the money columns to the precision the sample document displays.
 *
 * The screen shows unit prices to 4 d.p. (KES 1,850.0000) and discounts to
 * 6 d.p. (5.405405), which the original decimal(18,3) columns could not hold.
 * Totals are stored at 4 d.p. as well and merely displayed to 2, so a rounded
 * presentation never becomes a rounded stored value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('price_before_discount', 18, 4)->default(0)->change();
            $table->decimal('price_after_discount', 18, 4)->default(0)->change();
            $table->decimal('gross_price_after_discount', 18, 4)->default(0)->change();
            $table->decimal('discount_percent', 18, 6)->default(0)->change();
            $table->decimal('line_total', 18, 4)->default(0)->change();
            $table->decimal('gross_total', 18, 4)->default(0)->change();
            $table->decimal('vat_amount', 18, 4)->default(0)->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount_percent', 18, 6)->default(0)->change();
            $table->decimal('total_before_discount', 18, 4)->default(0)->change();
            $table->decimal('total_after_discount', 18, 4)->default(0)->change();
            $table->decimal('total_down_payment', 18, 4)->default(0)->change();
            $table->decimal('freight', 18, 4)->default(0)->change();
            $table->decimal('rounding', 18, 4)->default(0)->change();
            $table->decimal('tax_total', 18, 4)->default(0)->change();
            $table->decimal('document_total', 18, 4)->default(0)->change();
            $table->decimal('applied_amount', 18, 4)->default(0)->change();
            $table->decimal('balance_due', 18, 4)->default(0)->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->decimal('unit_price', 18, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('unit_price', 18, 3)->default(0)->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            foreach ([
                'discount_percent', 'total_before_discount', 'total_after_discount',
                'total_down_payment', 'freight', 'rounding', 'tax_total',
                'document_total', 'applied_amount', 'balance_due',
            ] as $column) {
                $table->decimal($column, 18, 3)->default(0)->change();
            }
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            foreach ([
                'price_before_discount', 'price_after_discount', 'gross_price_after_discount',
                'discount_percent', 'line_total', 'gross_total', 'vat_amount',
            ] as $column) {
                $table->decimal($column, 18, 3)->default(0)->change();
            }
        });
    }
};
