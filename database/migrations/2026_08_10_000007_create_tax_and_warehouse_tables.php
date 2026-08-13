<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VAT codes and warehouses.
 *
 * The sample screen carries a VAT Code per line and shows both net and gross
 * figures, so tax has to be a first-class part of the document rather than a
 * number typed into a totals box.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();          // O0, V16, E
            $table->string('name', 100);
            $table->decimal('rate', 8, 3)->default(0);     // percentage
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();          // FG WHS
            $table->string('name', 100);
            $table->string('location', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('vat_codes');
    }
};
