<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $origin
 * @property string $destination
 * @property numeric|null $distance_km
 * @property int|null $estimated_hours
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property numeric|null $origin_latitude
 * @property numeric|null $origin_longitude
 * @property numeric|null $destination_latitude
 * @property numeric|null $destination_longitude
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read string $path
 * @property-read Collection<int, Trip> $trips
 * @property-read int|null $trips_count
 *
 * @method static Builder<static>|Route newModelQuery()
 * @method static Builder<static>|Route newQuery()
 * @method static Builder<static>|Route query()
 * @method static Builder<static>|Route whereCode($value)
 * @method static Builder<static>|Route whereCreatedAt($value)
 * @method static Builder<static>|Route whereDestination($value)
 * @method static Builder<static>|Route whereDestinationLatitude($value)
 * @method static Builder<static>|Route whereDestinationLongitude($value)
 * @method static Builder<static>|Route whereDistanceKm($value)
 * @method static Builder<static>|Route whereEstimatedHours($value)
 * @method static Builder<static>|Route whereId($value)
 * @method static Builder<static>|Route whereIsActive($value)
 * @method static Builder<static>|Route whereName($value)
 * @method static Builder<static>|Route whereOrigin($value)
 * @method static Builder<static>|Route whereOriginLatitude($value)
 * @method static Builder<static>|Route whereOriginLongitude($value)
 * @method static Builder<static>|Route whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Route extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'origin', 'destination',
        'distance_km', 'estimated_hours', 'is_active',
        'origin_latitude', 'origin_longitude',
        'destination_latitude', 'destination_longitude',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'estimated_hours' => 'decimal:2',
            'is_active' => 'boolean',
            'origin_latitude' => 'decimal:7',
            'origin_longitude' => 'decimal:7',
            'destination_latitude' => 'decimal:7',
            'destination_longitude' => 'decimal:7',
        ];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function getPathAttribute(): string
    {
        return "{$this->origin} → {$this->destination}";
    }

    /**
     * Whether both ends have been pinned, and the route can be drawn.
     */
    public function isMappable(): bool
    {
        return $this->origin_latitude !== null
            && $this->origin_longitude !== null
            && $this->destination_latitude !== null
            && $this->destination_longitude !== null;
    }

    /**
     * Great-circle distance between the two ends, in kilometres.
     *
     * The road is always longer than the straight line — this is a floor, not
     * a substitute for what the odometer says — so it is offered as a
     * suggestion on the form rather than written over a figure someone entered.
     */
    public function greatCircleKm(): ?float
    {
        if (! $this->isMappable()) {
            return null;
        }

        $earth = 6371.0;
        $lat1 = deg2rad((float) $this->origin_latitude);
        $lat2 = deg2rad((float) $this->destination_latitude);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $this->destination_longitude - (float) $this->origin_longitude);

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return round($earth * 2 * asin(min(1.0, sqrt($a))), 2);
    }
}
