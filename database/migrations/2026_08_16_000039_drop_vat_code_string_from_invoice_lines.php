<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A line stops carrying the VAT code's name alongside its id.
 *
 * invoice_lines held both vat_code_id and vat_code — the second being the
 * `code` of the row the first already points at. Nothing read it; it was
 * written and never used.
 *
 * vat_rate stays, and is not the same kind of thing at all. Rates change by
 * legislation — Kenya moved 16% to 14% in April 2020 and back in January 2021 —
 * and an invoice already issued has to keep saying what the customer was
 * actually charged. Re-deriving it from vat_codes.rate would retroactively
 * falsify a tax record, so that snapshot is the one column here worth keeping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn('vat_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('vat_code', 16)->nullable();
        });

        DB::statement('
            UPDATE l SET l.vat_code = v.code
            FROM invoice_lines l INNER JOIN vat_codes v ON v.id = l.vat_code_id
        ');
    }
};
