<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connects a trip to the order it carries, and gives the order somewhere to
 * keep the customer's rating.
 *
 * Without the link the lifecycle stops at "paid": dispatch and delivery are
 * facts about a trip, and nothing said which order that trip was for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            /*
             * Nullable on purpose. Not every trip carries a sold order - a
             * repositioning run or a return leg is still a trip, and forcing an
             * invoice onto it would mean inventing one.
             *
             * nullOnDelete rather than cascade: deleting an invoice must not
             * silently delete the record that a vehicle made a journey.
             */
            $table->foreignId('invoice_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedTinyInteger('delivery_rating')->nullable();
            $table->string('delivery_rating_comment', 500)->nullable();
            $table->dateTime('delivery_rated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invoice_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['delivery_rating', 'delivery_rating_comment', 'delivery_rated_at']);
        });
    }
};
