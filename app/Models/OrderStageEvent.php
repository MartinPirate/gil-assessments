<?php

namespace App\Models;

use App\Enums\OrderStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One milestone in an order's life. Written once, never updated.
 */
class OrderStageEvent extends Model
{
    protected $fillable = [
        'invoice_id', 'stage', 'occurred_at', 'causer_id', 'causer_name', 'note', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'stage' => OrderStage::class,
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /** Oldest first — the order things actually happened in. */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('occurred_at')->orderBy('id');
    }
}
