<?php

namespace App\Models;

use App\Enums\OrderStage;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One milestone in an order's life. Written once, never updated.
 *
 * @property int $id
 * @property int $invoice_id
 * @property OrderStage $stage
 * @property Carbon $occurred_at
 * @property int|null $causer_id
 * @property string|null $causer_name
 * @property string|null $note
 * @property array<array-key, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $causer
 * @property-read Invoice $invoice
 *
 * @method static Builder<static>|OrderStageEvent chronological()
 * @method static Builder<static>|OrderStageEvent newModelQuery()
 * @method static Builder<static>|OrderStageEvent newQuery()
 * @method static Builder<static>|OrderStageEvent query()
 * @method static Builder<static>|OrderStageEvent whereCauserId($value)
 * @method static Builder<static>|OrderStageEvent whereCauserName($value)
 * @method static Builder<static>|OrderStageEvent whereCreatedAt($value)
 * @method static Builder<static>|OrderStageEvent whereId($value)
 * @method static Builder<static>|OrderStageEvent whereInvoiceId($value)
 * @method static Builder<static>|OrderStageEvent whereMeta($value)
 * @method static Builder<static>|OrderStageEvent whereNote($value)
 * @method static Builder<static>|OrderStageEvent whereOccurredAt($value)
 * @method static Builder<static>|OrderStageEvent whereStage($value)
 * @method static Builder<static>|OrderStageEvent whereUpdatedAt($value)
 *
 * @mixin Eloquent
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
