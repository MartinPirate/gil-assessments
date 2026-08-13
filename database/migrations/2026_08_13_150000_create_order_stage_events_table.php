<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The order lifecycle: one row per stage an order actually reached.
 *
 * Storing the stages rather than deriving them keeps the history honest. A
 * derived timeline can only ever describe the present — it would say an order
 * was dispatched "now" because the trip is in transit now, and would lose the
 * moment entirely once the trip completed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_stage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 32);
            $table->dateTime('occurred_at');

            // Who caused it. Null is legitimate: a payment settles an order
            // from a Safaricom callback, with no user in the request at all.
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('causer_name')->nullable();

            $table->string('note', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            /*
             * A stage happens once per order. This is the constraint the
             * recording service relies on to stay idempotent under concurrency:
             * a Safaricom retry and a manual allocation racing on the same
             * invoice both try to write "paid", and the database decides.
             */
            $table->unique(['invoice_id', 'stage']);
            $table->index(['invoice_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_stage_events');
    }
};
