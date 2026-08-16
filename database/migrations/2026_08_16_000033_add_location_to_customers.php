<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the customer actually is.
 *
 * Until now a business partner had a name, a PIN and a contact, but nowhere to
 * deliver to — the only "where" in the system was a route's free-text
 * destination, which names a corridor rather than a doorstep.
 *
 * Nullable throughout: the walk-in counter customer has no delivery address,
 * and an existing partner should not become unsaveable because a field nobody
 * has filled in yet is suddenly required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address_line', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('county', 100)->nullable();
            $table->string('postal_code', 20)->nullable();

            // Seven decimal places is roughly a centimetre — far past what a
            // delivery point needs, and the conventional width for these.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Deliveries are planned by town, so this is the one worth an index.
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropColumn(['address_line', 'city', 'county', 'postal_code', 'latitude', 'longitude']);
        });
    }
};
