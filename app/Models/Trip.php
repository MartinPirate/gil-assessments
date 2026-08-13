<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use Auditable;

    public const STATUS_SCHEDULED = 'Scheduled';
    public const STATUS_IN_TRANSIT = 'In Transit';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'reference', 'route_id', 'vehicle_id', 'driver_id',
        'route_name', 'vehicle_number', 'driver_name',
        'scheduled_at', 'departed_at', 'arrived_at',
        'status', 'cargo_description', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function gateLogs(): HasMany
    {
        return $this->hasMany(GateLog::class)->latest('time_in');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Trips a driver still has to run. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_IN_TRANSIT]);
    }

    /** Restricts a query to one driver — the driver portal depends on this. */
    public function scopeForDriver(Builder $query, ?int $driverId): Builder
    {
        // A null driver must match nothing, never everything.
        return $query->where('driver_id', $driverId ?? 0);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_SCHEDULED => self::STATUS_SCHEDULED,
            self::STATUS_IN_TRANSIT => self::STATUS_IN_TRANSIT,
            self::STATUS_COMPLETED => self::STATUS_COMPLETED,
            self::STATUS_CANCELLED => self::STATUS_CANCELLED,
        ];
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->departed_at) {
            return null;
        }

        $minutes = (int) $this->departed_at->diffInMinutes($this->arrived_at ?? now());

        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }
}
