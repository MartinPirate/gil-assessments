<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $vehicle_number
 * @property string|null $make
 * @property string|null $vehicle_type
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read Collection<int, Driver> $drivers
 * @property-read int|null $drivers_count
 * @property-read Collection<int, GateLog> $gateLogs
 * @property-read int|null $gate_logs_count
 * @property-read float $distance_covered
 * @property-read bool $is_on_site
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, Route> $routes
 * @property-read int|null $routes_count
 * @property-read Collection<int, Trip> $trips
 * @property-read int|null $trips_count
 *
 * @method static Builder<static>|Vehicle currentlyGatedIn()
 * @method static Builder<static>|Vehicle newModelQuery()
 * @method static Builder<static>|Vehicle newQuery()
 * @method static Builder<static>|Vehicle query()
 * @method static Builder<static>|Vehicle whereCreatedAt($value)
 * @method static Builder<static>|Vehicle whereId($value)
 * @method static Builder<static>|Vehicle whereIsActive($value)
 * @method static Builder<static>|Vehicle whereMake($value)
 * @method static Builder<static>|Vehicle whereUpdatedAt($value)
 * @method static Builder<static>|Vehicle whereVehicleNumber($value)
 * @method static Builder<static>|Vehicle whereVehicleType($value)
 *
 * @mixin Eloquent
 */
class Vehicle extends Model implements HasMedia
{
    /** Photographs of the vehicle itself — the fleet record, not a document. */
    public const string PHOTOS = 'photos';

    use Auditable;
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = ['vehicle_number', 'make', 'vehicle_type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function gateLogs(): HasMany
    {
        return $this->hasMany(GateLog::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class)->latest('scheduled_at');
    }

    /**
     * Everyone who has driven this vehicle, most recent first.
     *
     * Drawn from trips rather than from a driver_id on the vehicle: a truck has
     * no one permanent driver, it has whoever took it out last.
     *
     * @return BelongsToMany<Driver, $this>
     */
    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'trips')
            ->withPivot(['reference', 'status', 'scheduled_at', 'arrived_at'])
            ->distinct();
    }

    /**
     * Routes this vehicle has actually run.
     *
     * @return BelongsToMany<Route, $this>
     */
    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'trips')
            ->withPivot(['reference', 'status', 'scheduled_at']);
    }

    /** True while the vehicle is inside the yard. */
    public function getIsOnSiteAttribute(): bool
    {
        return $this->gateLogs()->where('status', GateLog::STATUS_IN)->exists();
    }

    /**
     * Total distance run, from the routes of completed trips only.
     *
     * A scheduled or cancelled trip has covered nothing, and counting it would
     * overstate the odometer.
     */
    public function getDistanceCoveredAttribute(): float
    {
        return (float) $this->trips()
            ->where('trips.status', Trip::STATUS_COMPLETED)
            ->join('routes', 'routes.id', '=', 'trips.route_id')
            ->sum('routes.distance_km');
    }

    /**
     * The open gate-in record for this vehicle, if it is currently on site.
     */
    public function openGateLog(): ?GateLog
    {
        return $this->gateLogs()->where('status', GateLog::STATUS_IN)->latest('time_in')->first();
    }

    /**
     * Task 2c: the Gate Out screen must only list vehicles currently gated in.
     */
    public function scopeCurrentlyGatedIn(Builder $query): Builder
    {
        return $query->whereHas('gateLogs', fn (Builder $q) => $q->where('status', GateLog::STATUS_IN));
    }

    /**
     * A vehicle may have several photographs — the plate, the body, damage
     * noted at the gate — and the first is the one screens lead with.
     *
     * A thumbnail conversion is registered so the fleet list is not pulling
     * full-size photographs down to render a 40px avatar.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTOS)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 320, 200)
            ->nonQueued();
    }

    public function photo(): ?Media
    {
        return $this->getFirstMedia(self::PHOTOS);
    }

    public function hasPhoto(): bool
    {
        return $this->photo() !== null;
    }
}
