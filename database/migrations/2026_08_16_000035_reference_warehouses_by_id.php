<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouses are referenced by id, not by their code spelled out again.
 *
 * items.warehouse and invoice_lines.warehouse held strings like 'FG WHS' while
 * a warehouses table sat alongside with those same codes. Unlike the item
 * columns just removed, these were not copies of anything — a line's warehouse
 * is a real per-line choice — but a free string cannot be constrained: nothing
 * stopped a code that matches no warehouse, and renaming a warehouse would
 * quietly orphan every row naming it.
 *
 * items.warehouse_id is NOT NULL, because stock has to sit somewhere.
 * invoice_lines.warehouse_id is nullable, because a service line ships nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable();
        });

        DB::statement('
            UPDATE i SET i.warehouse_id = w.id
            FROM items i INNER JOIN warehouses w ON w.code = i.warehouse
        ');

        // Anything whose code matched no warehouse falls back to the default,
        // so the column can be made NOT NULL without dropping rows.
        DB::statement('
            UPDATE items SET warehouse_id = (SELECT TOP 1 id FROM warehouses ORDER BY is_default DESC, id)
            WHERE warehouse_id IS NULL
        ');

        DB::statement('ALTER TABLE items ALTER COLUMN warehouse_id BIGINT NOT NULL');

        Schema::table('items', function (Blueprint $table) {
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->dropColumn('warehouse');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        });

        DB::statement('
            UPDATE l SET l.warehouse_id = w.id
            FROM invoice_lines l INNER JOIN warehouses w ON w.code = l.warehouse
        ');

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn('warehouse');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('warehouse', 32)->nullable();
        });

        DB::statement('
            UPDATE l SET l.warehouse = w.code
            FROM invoice_lines l INNER JOIN warehouses w ON w.id = l.warehouse_id
        ');

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('warehouse', 32)->default('FG WHS');
        });

        DB::statement('
            UPDATE i SET i.warehouse = w.code
            FROM items i INNER JOIN warehouses w ON w.id = i.warehouse_id
        ');

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
