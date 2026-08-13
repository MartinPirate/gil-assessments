<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    use Auditable;
    public const STATUS_PENDING = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REJECTED = 'Rejected';

    protected $fillable = [
        'invoice_id', 'amount', 'threshold', 'status',
        'requested_by', 'requested_at', 'decided_by', 'decided_at', 'decision_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'threshold' => 'decimal:3',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
