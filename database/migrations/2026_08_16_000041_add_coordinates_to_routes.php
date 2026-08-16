<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puts a route on a map.
 *
 * origin and destination were free text — "Nairobi", "Mombasa" — which names a
 * corridor but cannot be drawn. Both ends get coordinates, and distance_km
 * stops being a number somebody typed from memory: it is computed from the two
 * points when they are set.
 *
 * Nullable, because a route whose ends nobody has pinned yet is still a valid
 * route; it simply does not appear on the map.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            // Seven decimal places is about a centimetre — far more than a
            // depot needs, and the conventional width for these.
            $table->decimal('origin_latitude', 10, 7)->nullable();
            $table->decimal('origin_longitude', 10, 7)->nullable();
            $table->decimal('destination_latitude', 10, 7)->nullable();
            $table->decimal('destination_longitude', 10, 7)->nullable();
        });

        // The seeded corridors, pinned at the towns they name.
        $towns = [
            'RT-NBO-MSA' => [-1.2921, 36.8219, -4.0435, 39.6682],
            'RT-NBO-KSM' => [-1.2921, 36.8219, -0.0917, 34.7680],
            'RT-NBO-NKR' => [-1.2921, 36.8219, -0.3031, 36.0800],
            'RT-NBO-ELD' => [-1.2921, 36.8219, 0.5143, 35.2698],
            'RT-CITY' => [-1.2864, 36.8172, -1.3180, 36.8300],
        ];

        foreach ($towns as $code => [$oLat, $oLng, $dLat, $dLng]) {
            DB::table('routes')->where('code', $code)->update([
                'origin_latitude' => $oLat,
                'origin_longitude' => $oLng,
                'destination_latitude' => $dLat,
                'destination_longitude' => $dLng,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn([
                'origin_latitude',
                'origin_longitude',
                'destination_latitude',
                'destination_longitude',
            ]);
        });
    }
};
