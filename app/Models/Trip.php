<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int $route_id
 * @property int $vehicle_id
 * @property int $driver_id
 * @property string $route_name
 * @property string $vehicle_number
 * @property string $driver_name
 * @property Carbon $scheduled_at
 * @property Carbon|null $departed_at
 * @property Carbon|null $arrived_at
 * @property string $status
 * @property string|null $cargo_description
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $invoice_id
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read User|null $creator
 * @property-read Driver $driver
 * @property-read Collection<int, GateLog> $gateLogs
 * @property-read int|null $gate_logs_count
 * @property-read string|null $duration
 * @property-read Invoice|null $invoice
 * @property-read Route $route
 * @property-read Vehicle $vehicle
 *
 * @method static Builder<static>|Trip forDriver(?int $driverId)
 * @method static Builder<static>|Trip newModelQuery()
 * @method static Builder<static>|Trip newQuery()
 * @method static Builder<static>|Trip open()
 * @method static Builder<static>|Trip query()
 * @method static Builder<static>|Trip whereArrivedAt($value)
 * @method static Builder<static>|Trip whereCargoDescription($value)
 * @method static Builder<static>|Trip whereCreatedAt($value)
 * @method static Builder<static>|Trip whereCreatedBy($value)
 * @method static Builder<static>|Trip whereDepartedAt($value)
 * @method static Builder<static>|Trip whereDriverId($value)
 * @method static Builder<static>|Trip whereDriverName($value)
 * @method static Builder<static>|Trip whereId($value)
 * @method static Builder<static>|Trip whereInvoiceId($value)
 * @method static Builder<static>|Trip whereNotes($value)
 * @method static Builder<static>|Trip whereReference($value)
 * @method static Builder<static>|Trip whereRouteId($value)
 * @method static Builder<static>|Trip whereRouteName($value)
 * @method static Builder<static>|Trip whereScheduledAt($value)
 * @method static Builder<static>|Trip whereStatus($value)
 * @method static Builder<static>|Trip whereUpdatedAt($value)
 * @method static Builder<static>|Trip whereVehicleId($value)
 * @method static Builder<static>|Trip whereVehicleNumber($value)
 *
 * @mixin Eloquent
 */
class Trip extends Model
{
    use Auditable;

    public const string STATUS_SCHEDULED = 'Scheduled';

    public const string STATUS_IN_TRANSIT = 'In Transit';

    public const string STATUS_COMPLETED = 'Completed';

    public const string STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'reference', 'invoice_id', 'route_id', 'vehicle_id', 'driver_id',
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

    /** The order this trip is carrying, when it is carrying one. */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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

        $end = $this->arrived_at ?? now();

        /*
         * An arrival before the departure is bad data, not a short trip, and
         * the naive subtraction rendered it as "-136h -6m" on the driver's own
         * screen. Nothing at all is the honest answer; the row is hidden rather
         * than filled with a number that cannot be true.
         */
        if ($end->lessThan($this->departed_at)) {
            return null;
        }

        $minutes = (int) $this->departed_at->diffInMinutes($end);

        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }
}
