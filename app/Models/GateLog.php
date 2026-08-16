<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vehicle_id
 * @property int $driver_id
 * @property Carbon $time_in
 * @property Carbon|null $time_out
 * @property int $gated_in_by
 * @property int|null $gated_out_by
 * @property string $status
 * @property string|null $gate_in_remarks
 * @property string|null $gate_out_remarks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $trip_id
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read Driver $driver
 * @property-read User $gatedInBy
 * @property-read User|null $gatedOutBy
 * @property-read string|null $duration
 * @property-read Vehicle $vehicle
 *
 * @method static Builder<static>|GateLog newModelQuery()
 * @method static Builder<static>|GateLog newQuery()
 * @method static Builder<static>|GateLog open()
 * @method static Builder<static>|GateLog query()
 * @method static Builder<static>|GateLog whereCreatedAt($value)
 * @method static Builder<static>|GateLog whereDriverId($value)
 * @method static Builder<static>|GateLog whereGateInRemarks($value)
 * @method static Builder<static>|GateLog whereGateOutRemarks($value)
 * @method static Builder<static>|GateLog whereGatedInBy($value)
 * @method static Builder<static>|GateLog whereGatedOutBy($value)
 * @method static Builder<static>|GateLog whereId($value)
 * @method static Builder<static>|GateLog whereStatus($value)
 * @method static Builder<static>|GateLog whereTimeIn($value)
 * @method static Builder<static>|GateLog whereTimeOut($value)
 * @method static Builder<static>|GateLog whereTripId($value)
 * @method static Builder<static>|GateLog whereUpdatedAt($value)
 * @method static Builder<static>|GateLog whereVehicleId($value)
 *
 * @mixin Eloquent
 */
class GateLog extends Model
{
    use Auditable;
    use HasFactory;

    public const STATUS_IN = 'IN';

    public const STATUS_OUT = 'OUT';

    protected $fillable = [
        'vehicle_id', 'driver_id', 'time_in', 'time_out',
        'gated_in_by', 'gated_out_by', 'status',
        'gate_in_remarks', 'gate_out_remarks',
    ];

    protected function casts(): array
    {
        return [
            'time_in' => 'datetime',
            'time_out' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function gatedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gated_in_by');
    }

    public function gatedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gated_out_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN);
    }

    /**
     * How long the vehicle has been (or was) on site, as "2h 15m".
     */
    public function getDurationAttribute(): ?string
    {
        if (! $this->time_in) {
            return null;
        }

        $minutes = $this->time_in->diffInMinutes($this->time_out ?? now());

        return sprintf('%dh %02dm', intdiv((int) $minutes, 60), (int) $minutes % 60);
    }
}
