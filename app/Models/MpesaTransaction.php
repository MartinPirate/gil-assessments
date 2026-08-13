<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    use HasFactory;

    public const TYPE_VALIDATION = 'validation';
    public const TYPE_CONFIRMATION = 'confirmation';

    public const ALLOCATION_PENDING = 'Pending';
    public const ALLOCATION_MATCHED = 'Matched';
    public const ALLOCATION_PARTIAL = 'Partial';
    public const ALLOCATION_UNMATCHED = 'Unmatched';
    /** A failed or cancelled STK push: recorded, but no money to allocate. */
    public const ALLOCATION_NOT_APPLICABLE = 'N/A';

    protected $fillable = [
        'transaction_type', 'trans_id', 'trans_time', 'trans_amount',
        'business_short_code', 'bill_ref_number', 'invoice_number',
        'org_account_balance', 'third_party_trans_id', 'msisdn',
        'first_name', 'middle_name', 'last_name',
        'callback_type', 'raw_payload', 'received_at',
        'allocation_status', 'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'received_at' => 'datetime',
            'allocated_amount' => 'decimal:3',
        ];
    }

    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * How much of this receipt has not yet been applied to an invoice.
     */
    public function getUnallocatedAmountAttribute(): float
    {
        return round((float) $this->trans_amount - (float) $this->allocated_amount, 3);
    }

    /**
     * TransTime arrives as yyyyMMddHHmmss. Kept as a string in the column per
     * the spec; this exposes it as a date for display without mutating storage.
     */
    public function getTransactedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        if (! $this->trans_time || strlen($this->trans_time) !== 14) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('YmdHis', $this->trans_time);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function getPayerNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name, $this->middle_name, $this->last_name,
        ])));
    }
}
