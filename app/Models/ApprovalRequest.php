<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property numeric $amount
 * @property numeric $threshold
 * @property string $status
 * @property int|null $requested_by
 * @property Carbon $requested_at
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read User|null $decider
 * @property-read Invoice $invoice
 * @property-read User|null $requester
 *
 * @method static Builder<static>|ApprovalRequest newModelQuery()
 * @method static Builder<static>|ApprovalRequest newQuery()
 * @method static Builder<static>|ApprovalRequest pending()
 * @method static Builder<static>|ApprovalRequest query()
 * @method static Builder<static>|ApprovalRequest whereAmount($value)
 * @method static Builder<static>|ApprovalRequest whereCreatedAt($value)
 * @method static Builder<static>|ApprovalRequest whereDecidedAt($value)
 * @method static Builder<static>|ApprovalRequest whereDecidedBy($value)
 * @method static Builder<static>|ApprovalRequest whereDecisionReason($value)
 * @method static Builder<static>|ApprovalRequest whereId($value)
 * @method static Builder<static>|ApprovalRequest whereInvoiceId($value)
 * @method static Builder<static>|ApprovalRequest whereRequestedAt($value)
 * @method static Builder<static>|ApprovalRequest whereRequestedBy($value)
 * @method static Builder<static>|ApprovalRequest whereStatus($value)
 * @method static Builder<static>|ApprovalRequest whereThreshold($value)
 * @method static Builder<static>|ApprovalRequest whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ApprovalRequest extends Model
{
    use Auditable;

    public const string STATUS_PENDING = 'Pending';

    public const string STATUS_APPROVED = 'Approved';

    public const string STATUS_REJECTED = 'Rejected';

    protected $fillable = [
        'invoice_id',
        'amount',
        'threshold',
        'status',
        'requested_by',
        'requested_at',
        'decided_by',
        'decided_at',
        'decision_reason',
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
