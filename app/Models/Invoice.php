<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use Auditable;
    use HasFactory;

    /** Documents above this total are routed for approval (Task 1b). */
    public const APPROVAL_THRESHOLD = 10000;

    public const TYPE_INVOICE = 'Invoice';
    public const TYPE_DRAFT = 'Draft';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_OPEN = 'Open';
    public const STATUS_PENDING_APPROVAL = 'Pending Approval';
    public const STATUS_REJECTED = 'Rejected';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'doc_num', 'series', 'doc_type', 'customer_id', 'customer_code', 'customer_name',
        'customer_display_name', 'item_service_type', 'qr_code',
        'contact_person', 'kra_pin', 'currency',
        'posting_date', 'value_date', 'document_date',
        'sales_employee_id', 'sales_employee_name', 'owner_id', 'owner_name',
        'summary_type', 'payment_order_run', 'remarks',
        'total_before_discount', 'discount_percent', 'total_after_discount',
        'total_down_payment', 'freight', 'rounding_enabled', 'rounding',
        'tax_total', 'document_total', 'applied_amount', 'balance_due',
        'requires_approval', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'value_date' => 'date',
            'document_date' => 'date',
            'total_before_discount' => 'decimal:4',
            'discount_percent' => 'decimal:6',
            'total_after_discount' => 'decimal:4',
            'total_down_payment' => 'decimal:4',
            'freight' => 'decimal:4',
            'rounding' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'document_total' => 'decimal:4',
            'applied_amount' => 'decimal:4',
            'balance_due' => 'decimal:4',
            'requires_approval' => 'boolean',
            'rounding_enabled' => 'boolean',
            'payment_order_run' => 'boolean',
        ];
    }

    /* ----------------------------------------------------------------- */

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('line_num');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesEmployee(): BelongsTo
    {
        return $this->belongsTo(SalesEmployee::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class)->latest('requested_at');
    }

    public function pendingApproval(): HasOne
    {
        return $this->hasOne(ApprovalRequest::class)
            ->where('status', ApprovalRequest::STATUS_PENDING);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** The order's lifecycle, oldest milestone first. */
    public function stageEvents(): HasMany
    {
        return $this->hasMany(OrderStageEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    /** The trips carrying this order. */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /* ----------------------------------------------------------------- */

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where('doc_type', self::TYPE_DRAFT);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('doc_type', self::TYPE_INVOICE);
    }

    /**
     * Documents a payment may still be applied to.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->posted()
            ->where('balance_due', '>', 0)
            ->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_REJECTED]);
    }

    public function getDocumentNumberAttribute(): string
    {
        return $this->series.'-'.str_pad((string) $this->doc_num, 8, '0', STR_PAD_LEFT);
    }

    public function isDraft(): bool
    {
        return $this->doc_type === self::TYPE_DRAFT;
    }

    /**
     * A draft is not a receivable, and a cancelled or rejected document is not
     * collectable, so neither should attract payment.
     */
    public function acceptsPayment(): bool
    {
        return ! $this->isDraft()
            && ! in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_REJECTED], true)
            && (float) $this->balance_due > 0;
    }
}
