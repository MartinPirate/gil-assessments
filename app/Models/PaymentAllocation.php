<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $mpesa_transaction_id
 * @property int $invoice_id
 * @property numeric $amount
 * @property string $matched_by
 * @property int|null $allocated_by
 * @property Carbon $allocated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $allocatedBy
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read Invoice $invoice
 * @property-read MpesaTransaction $transaction
 *
 * @method static Builder<static>|PaymentAllocation newModelQuery()
 * @method static Builder<static>|PaymentAllocation newQuery()
 * @method static Builder<static>|PaymentAllocation query()
 * @method static Builder<static>|PaymentAllocation whereAllocatedAt($value)
 * @method static Builder<static>|PaymentAllocation whereAllocatedBy($value)
 * @method static Builder<static>|PaymentAllocation whereAmount($value)
 * @method static Builder<static>|PaymentAllocation whereCreatedAt($value)
 * @method static Builder<static>|PaymentAllocation whereId($value)
 * @method static Builder<static>|PaymentAllocation whereInvoiceId($value)
 * @method static Builder<static>|PaymentAllocation whereMatchedBy($value)
 * @method static Builder<static>|PaymentAllocation whereMpesaTransactionId($value)
 * @method static Builder<static>|PaymentAllocation whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class PaymentAllocation extends Model
{
    use Auditable;

    public const MATCHED_AUTO = 'auto';

    public const MATCHED_MANUAL = 'manual';

    protected $fillable = [
        'mpesa_transaction_id', 'invoice_id', 'amount',
        'matched_by', 'allocated_by', 'allocated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'allocated_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(MpesaTransaction::class, 'mpesa_transaction_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
