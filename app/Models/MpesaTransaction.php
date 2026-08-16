<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * @property int $id
 * @property string|null $transaction_type
 * @property string $trans_id
 * @property string|null $trans_time
 * @property string|null $trans_amount
 * @property string|null $business_short_code
 * @property string|null $bill_ref_number
 * @property string|null $invoice_number
 * @property string|null $org_account_balance
 * @property string|null $third_party_trans_id
 * @property string|null $msisdn
 * @property string|null $first_name
 * @property string|null $middle_name
 * @property string|null $last_name
 * @property string $callback_type
 * @property array<array-key, mixed> $raw_payload
 * @property Carbon $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $allocation_status
 * @property numeric $allocated_amount
 * @property-read Collection<int, PaymentAllocation> $allocations
 * @property-read int|null $allocations_count
 * @property-read string $payer_name
 * @property-read Carbon|null $transacted_at
 * @property-read float $unallocated_amount
 *
 * @method static Builder<static>|MpesaTransaction newModelQuery()
 * @method static Builder<static>|MpesaTransaction newQuery()
 * @method static Builder<static>|MpesaTransaction query()
 * @method static Builder<static>|MpesaTransaction whereAllocatedAmount($value)
 * @method static Builder<static>|MpesaTransaction whereAllocationStatus($value)
 * @method static Builder<static>|MpesaTransaction whereBillRefNumber($value)
 * @method static Builder<static>|MpesaTransaction whereBusinessShortCode($value)
 * @method static Builder<static>|MpesaTransaction whereCallbackType($value)
 * @method static Builder<static>|MpesaTransaction whereCreatedAt($value)
 * @method static Builder<static>|MpesaTransaction whereFirstName($value)
 * @method static Builder<static>|MpesaTransaction whereId($value)
 * @method static Builder<static>|MpesaTransaction whereInvoiceNumber($value)
 * @method static Builder<static>|MpesaTransaction whereLastName($value)
 * @method static Builder<static>|MpesaTransaction whereMiddleName($value)
 * @method static Builder<static>|MpesaTransaction whereMsisdn($value)
 * @method static Builder<static>|MpesaTransaction whereOrgAccountBalance($value)
 * @method static Builder<static>|MpesaTransaction whereRawPayload($value)
 * @method static Builder<static>|MpesaTransaction whereReceivedAt($value)
 * @method static Builder<static>|MpesaTransaction whereThirdPartyTransId($value)
 * @method static Builder<static>|MpesaTransaction whereTransAmount($value)
 * @method static Builder<static>|MpesaTransaction whereTransId($value)
 * @method static Builder<static>|MpesaTransaction whereTransTime($value)
 * @method static Builder<static>|MpesaTransaction whereTransactionType($value)
 * @method static Builder<static>|MpesaTransaction whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class MpesaTransaction extends Model
{
    use HasFactory;

    public const string TYPE_VALIDATION = 'validation';

    public const string TYPE_CONFIRMATION = 'confirmation';

    public const string ALLOCATION_PENDING = 'Pending';

    public const string ALLOCATION_MATCHED = 'Matched';

    public const string ALLOCATION_PARTIAL = 'Partial';

    public const string ALLOCATION_UNMATCHED = 'Unmatched';

    /** A failed or cancelled STK push: recorded, but no money to allocate. */
    public const string ALLOCATION_NOT_APPLICABLE = 'N/A';

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

    public function allocations(): HasMany
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
    public function getTransactedAtAttribute(): ?Carbon
    {
        if (! $this->trans_time || strlen($this->trans_time) !== 14) {
            return null;
        }

        try {
            return Carbon::createFromFormat('YmdHis', $this->trans_time);
        } catch (Throwable $e) {
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
