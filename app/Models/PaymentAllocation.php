<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
