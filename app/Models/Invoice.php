<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $doc_num
 * @property string $series
 * @property int $customer_id
 * @property string $customer_code
 * @property string $customer_name
 * @property string $currency
 * @property Carbon $posting_date
 * @property int|null $sales_employee_id
 * @property string|null $sales_employee_name
 * @property string $remarks
 * @property numeric $total_before_discount
 * @property numeric $discount_percent
 * @property numeric $total_after_discount
 * @property bool $requires_approval
 * @property string $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $doc_type
 * @property string|null $contact_person
 * @property string|null $kra_pin
 * @property Carbon|null $value_date
 * @property Carbon|null $document_date
 * @property int|null $owner_id
 * @property string|null $owner_name
 * @property string $summary_type
 * @property bool $payment_order_run
 * @property numeric $total_down_payment
 * @property numeric $freight
 * @property bool $rounding_enabled
 * @property numeric $rounding
 * @property numeric $tax_total
 * @property numeric $document_total
 * @property numeric $applied_amount
 * @property numeric $balance_due
 * @property string|null $customer_display_name
 * @property string $item_service_type
 * @property string|null $qr_code
 * @property int|null $delivery_rating
 * @property string|null $delivery_rating_comment
 * @property string|null $delivery_rated_at
 * @property string|null $etr_barcode
 * @property string|null $etr_scanned_at
 * @property-read Collection<int, PaymentAllocation> $allocations
 * @property-read int|null $allocations_count
 * @property-read Collection<int, ApprovalRequest> $approvalRequests
 * @property-read int|null $approval_requests_count
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read User|null $creator
 * @property-read Customer $customer
 * @property-read Collection<int, InvoiceFreightCharge> $freightCharges
 * @property-read int|null $freight_charges_count
 * @property-read string $document_number
 * @property-read Collection<int, InvoiceLine> $lines
 * @property-read int|null $lines_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read User|null $owner
 * @property-read ApprovalRequest|null $pendingApproval
 * @property-read SalesEmployee|null $salesEmployee
 * @property-read Collection<int, OrderStageEvent> $stageEvents
 * @property-read int|null $stage_events_count
 * @property-read Collection<int, Trip> $trips
 * @property-read int|null $trips_count
 *
 * @method static Builder<static>|Invoice drafts()
 * @method static Builder<static>|Invoice newModelQuery()
 * @method static Builder<static>|Invoice newQuery()
 * @method static Builder<static>|Invoice outstanding()
 * @method static Builder<static>|Invoice posted()
 * @method static Builder<static>|Invoice query()
 * @method static Builder<static>|Invoice whereAppliedAmount($value)
 * @method static Builder<static>|Invoice whereBalanceDue($value)
 * @method static Builder<static>|Invoice whereContactPerson($value)
 * @method static Builder<static>|Invoice whereCreatedAt($value)
 * @method static Builder<static>|Invoice whereCreatedBy($value)
 * @method static Builder<static>|Invoice whereCurrency($value)
 * @method static Builder<static>|Invoice whereCustomerCode($value)
 * @method static Builder<static>|Invoice whereCustomerDisplayName($value)
 * @method static Builder<static>|Invoice whereCustomerId($value)
 * @method static Builder<static>|Invoice whereCustomerName($value)
 * @method static Builder<static>|Invoice whereDeliveryRatedAt($value)
 * @method static Builder<static>|Invoice whereDeliveryRating($value)
 * @method static Builder<static>|Invoice whereDeliveryRatingComment($value)
 * @method static Builder<static>|Invoice whereDiscountPercent($value)
 * @method static Builder<static>|Invoice whereDocNum($value)
 * @method static Builder<static>|Invoice whereDocType($value)
 * @method static Builder<static>|Invoice whereDocumentDate($value)
 * @method static Builder<static>|Invoice whereDocumentTotal($value)
 * @method static Builder<static>|Invoice whereEtrBarcode($value)
 * @method static Builder<static>|Invoice whereEtrScannedAt($value)
 * @method static Builder<static>|Invoice whereFreight($value)
 * @method static Builder<static>|Invoice whereId($value)
 * @method static Builder<static>|Invoice whereItemServiceType($value)
 * @method static Builder<static>|Invoice whereKraPin($value)
 * @method static Builder<static>|Invoice whereOwnerId($value)
 * @method static Builder<static>|Invoice whereOwnerName($value)
 * @method static Builder<static>|Invoice wherePaymentOrderRun($value)
 * @method static Builder<static>|Invoice wherePostingDate($value)
 * @method static Builder<static>|Invoice whereQrCode($value)
 * @method static Builder<static>|Invoice whereRemarks($value)
 * @method static Builder<static>|Invoice whereRequiresApproval($value)
 * @method static Builder<static>|Invoice whereRounding($value)
 * @method static Builder<static>|Invoice whereRoundingEnabled($value)
 * @method static Builder<static>|Invoice whereSalesEmployeeId($value)
 * @method static Builder<static>|Invoice whereSalesEmployeeName($value)
 * @method static Builder<static>|Invoice whereSeries($value)
 * @method static Builder<static>|Invoice whereStatus($value)
 * @method static Builder<static>|Invoice whereSummaryType($value)
 * @method static Builder<static>|Invoice whereTaxTotal($value)
 * @method static Builder<static>|Invoice whereTotalAfterDiscount($value)
 * @method static Builder<static>|Invoice whereTotalBeforeDiscount($value)
 * @method static Builder<static>|Invoice whereTotalDownPayment($value)
 * @method static Builder<static>|Invoice whereUpdatedAt($value)
 * @method static Builder<static>|Invoice whereValueDate($value)
 *
 * @mixin Eloquent
 */
class Invoice extends Model implements HasMedia
{
    use Auditable;
    use HasFactory;
    use InteractsWithMedia;

    /** The rendered document, and anything scanned back in against it. */
    public const string PDF = 'pdf';

    public const string ATTACHMENTS = 'attachments';

    /** Documents above this total are routed for approval (Task 1b). */
    public const int APPROVAL_THRESHOLD = 10000;

    public const string TYPE_INVOICE = 'Invoice';

    public const string TYPE_DRAFT = 'Draft';

    public const string STATUS_DRAFT = 'Draft';

    public const string STATUS_OPEN = 'Open';

    public const string STATUS_PENDING_APPROVAL = 'Pending Approval';

    public const string STATUS_REJECTED = 'Rejected';

    public const string STATUS_CLOSED = 'Closed';

    public const string STATUS_CANCELLED = 'Cancelled';

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
        'etr_barcode', 'etr_scanned_at',
        'delivery_rating', 'delivery_rating_comment', 'delivery_rated_at',
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

    /**
     * Two collections, because they answer different questions.
     *
     * `pdf` is the document this system produced — one file, replaced whenever
     * it is rendered again, so there is never a question of which PDF *is* the
     * invoice. `attachments` is everything that came back the other way: a
     * signed delivery note, a stamped copy, a customer's purchase order.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PDF)
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);

        $this->addMediaCollection(self::ATTACHMENTS)
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * The named charges making up this document's freight.
     */
    public function freightCharges(): HasMany
    {
        return $this->hasMany(InvoiceFreightCharge::class)->orderBy('line_num');
    }

    public function pdf(): ?Media
    {
        return $this->getFirstMedia(self::PDF);
    }

    public function hasPdf(): bool
    {
        return $this->pdf() !== null;
    }
}
