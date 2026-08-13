<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the invoice header and lines up to the full document shown in the
 * sample screen: value/document dates, owner, tax, freight, rounding,
 * down payments, and the applied/balance figures a real A/R document needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('doc_type', 16)->default('Invoice')->after('series'); // Invoice | Draft
            $table->string('contact_person', 150)->nullable()->after('customer_name');
            $table->string('kra_pin', 32)->nullable()->after('contact_person');

            $table->date('value_date')->nullable()->after('posting_date');
            $table->date('document_date')->nullable()->after('value_date');

            $table->foreignId('owner_id')->nullable()->after('sales_employee_name')->constrained('users');
            $table->string('owner_name', 150)->nullable()->after('owner_id');

            $table->string('summary_type', 32)->default('No Summary')->after('owner_name');
            $table->boolean('payment_order_run')->default(false)->after('summary_type');

            // Totals block, mirroring the screen bottom-right.
            $table->decimal('total_down_payment', 18, 3)->default(0)->after('total_after_discount');
            $table->decimal('freight', 18, 3)->default(0)->after('total_down_payment');
            $table->boolean('rounding_enabled')->default(false)->after('freight');
            $table->decimal('rounding', 18, 3)->default(0)->after('rounding_enabled');
            $table->decimal('tax_total', 18, 3)->default(0)->after('rounding');
            $table->decimal('document_total', 18, 3)->default(0)->after('tax_total');
            $table->decimal('applied_amount', 18, 3)->default(0)->after('document_total');
            $table->decimal('balance_due', 18, 3)->default(0)->after('applied_amount');

            $table->index('doc_type');
            $table->index('status');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('item_service_type', 16)->default('Item')->after('line_num');
            $table->decimal('qty_in_warehouse', 18, 3)->default(0)->after('quantity');

            $table->foreignId('vat_code_id')->nullable()->after('line_total')->constrained('vat_codes');
            $table->string('vat_code', 16)->nullable()->after('vat_code_id');
            $table->decimal('vat_rate', 8, 3)->default(0)->after('vat_code');
            $table->decimal('vat_amount', 18, 3)->default(0)->after('vat_rate');

            // "Gross" columns include VAT, as in the sample screen.
            $table->decimal('gross_price_after_discount', 18, 3)->default(0)->after('vat_amount');
            $table->decimal('gross_total', 18, 3)->default(0)->after('gross_price_after_discount');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vat_code_id');
            $table->dropColumn([
                'item_service_type', 'qty_in_warehouse', 'vat_code', 'vat_rate',
                'vat_amount', 'gross_price_after_discount', 'gross_total',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn([
                'doc_type', 'contact_person', 'kra_pin', 'value_date', 'document_date',
                'owner_name', 'summary_type', 'payment_order_run',
                'total_down_payment', 'freight', 'rounding_enabled', 'rounding',
                'tax_total', 'document_total', 'applied_amount', 'balance_due',
            ]);
        });
    }
};
