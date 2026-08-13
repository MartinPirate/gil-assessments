<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'origin', 'destination',
        'distance_km', 'estimated_hours', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'is_active' => 'boolean',
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
}
