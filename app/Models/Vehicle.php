<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
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
