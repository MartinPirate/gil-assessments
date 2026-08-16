<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A line stops carrying its own copy of the item number and unit of measure.
 *
 * The original table allowed for free-typed item numbers, but the screen never
 * grew a field for one: item_no was only ever written from the item the user
 * picked, and uom is shown disabled. Both were therefore copies of the master
 * row that nobody could make say anything different — so they read through the
 * item relation now.
 *
 * What stays, because it is not duplication:
 *   - item_description: editable per line, so a document may word it its own way
 *   - warehouse:        chosen per line, defaulting from the item
 *   - price_*:          a negotiated price belongs to the line, not the item
 *   - qty_in_warehouse: stock as it stood when the line was raised
 *
 * item_id stays nullable: a service line legitimately has no item behind it,
 * and it then has no number or unit either, which is correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['item_no', 'uom']);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('item_no', 32)->nullable();
            $table->string('uom', 16)->nullable();
        });

        DB::statement('
            UPDATE l
            SET l.item_no = i.item_no,
                l.uom = i.uom
            FROM invoice_lines l
            INNER JOIN items i ON i.id = l.item_id
        ');
    }
};
