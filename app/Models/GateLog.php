<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GateLog extends Model
{
    use Auditable;
    use HasFactory;

    public const STATUS_IN = 'IN';
    public const STATUS_OUT = 'OUT';

    protected $fillable = [
        'vehicle_id', 'vehicle_number', 'driver_id', 'driver_name',
        'driver_national_id', 'driver_phone', 'time_in', 'time_out',
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
