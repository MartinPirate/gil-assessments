<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use Auditable;
    use HasFactory;

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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Driver, $this>
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Route, $this>
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
}
