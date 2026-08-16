<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AR Invoice header + lines (Task 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // "No." on the screen: auto-incremented sequential document number.
            $table->unsignedBigInteger('doc_num')->unique();
            $table->string('series', 8)->default('IN');
            $table->foreignId('customer_id')->constrained();
            // Code/name are snapshotted so a later master-data edit cannot
            // rewrite the history of a posted document.
            $table->string('customer_code', 32);
            $table->string('customer_name', 150);
            $table->string('currency', 8)->default('KES');
            $table->date('posting_date');
            $table->foreignId('sales_employee_id')->nullable()->constrained();
            $table->string('sales_employee_name', 150)->nullable();
            $table->string('remarks', 1000);              // mandatory per spec
            $table->decimal('total_before_discount', 18, 3)->default(0);
            $table->decimal('discount_percent', 8, 3)->default(0);
            $table->decimal('total_after_discount', 18, 3)->default(0);
            // Set at save time when total_after_discount > 10,000.
            $table->boolean('requires_approval')->default(false);
            $table->string('status', 20)->default('Open');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('posting_date');
            $table->index('customer_code');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_num');
            $table->foreignId('item_id')->nullable()->constrained();
            // Free-typed item numbers are allowed by the spec, so this is a
            // plain string rather than a required FK.
            $table->string('item_no', 32)->nullable();
            $table->string('item_description', 200)->nullable();
            $table->string('uom', 16)->nullable();
            $table->string('warehouse', 32)->nullable();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('price_before_discount', 18, 3)->default(0);
            $table->decimal('discount_percent', 18, 3)->default(0);
            $table->decimal('price_after_discount', 18, 3)->default(0);
            $table->decimal('line_total', 18, 3)->default(0);
            $table->timestamps();

            $table->unique(['invoice_id', 'line_num']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
