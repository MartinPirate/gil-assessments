<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data shared by the AR Invoice screen (Task 1):
 * customers, items and sales employees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();       // CC00001
            $table->string('name', 150);
            $table->string('contact_person', 150)->nullable();
            $table->string('currency', 8)->default('KES');
            $table->string('kra_pin', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_no', 32)->unique();     // FG00011
            $table->string('description', 200);
            $table->string('uom', 16)->default('Bales');
            $table->string('warehouse', 32)->default('FG WHS');
            // Money/quantity columns are 3 d.p. throughout, per the spec.
            $table->decimal('unit_price', 18, 3)->default(0);
            $table->decimal('qty_in_warehouse', 18, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('description');
        });

        Schema::create('sales_employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->string('position', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_employees');
        Schema::dropIfExists('items');
        Schema::dropIfExists('customers');
    }
};
