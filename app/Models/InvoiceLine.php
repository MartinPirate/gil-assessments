<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $line_num
 * @property int|null $item_id
 * @property string|null $item_description
 * @property numeric $quantity
 * @property numeric $price_before_discount
 * @property numeric $discount_percent
 * @property numeric $price_after_discount
 * @property numeric $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $item_service_type
 * @property numeric $qty_in_warehouse
 * @property int|null $vat_code_id
 * @property numeric $vat_rate
 * @property numeric $vat_amount
 * @property numeric $gross_price_after_discount
 * @property numeric $gross_total
 * @property int|null $warehouse_id
 * @property-read Invoice $invoice
 * @property-read Item|null $item
 * @property-read VatCode|null $vatCode
 * @property-read Warehouse|null $warehouse
 *
 * @method static Builder<static>|InvoiceLine newModelQuery()
 * @method static Builder<static>|InvoiceLine newQuery()
 * @method static Builder<static>|InvoiceLine query()
 * @method static Builder<static>|InvoiceLine whereCreatedAt($value)
 * @method static Builder<static>|InvoiceLine whereDiscountPercent($value)
 * @method static Builder<static>|InvoiceLine whereGrossPriceAfterDiscount($value)
 * @method static Builder<static>|InvoiceLine whereGrossTotal($value)
 * @method static Builder<static>|InvoiceLine whereId($value)
 * @method static Builder<static>|InvoiceLine whereInvoiceId($value)
 * @method static Builder<static>|InvoiceLine whereItemDescription($value)
 * @method static Builder<static>|InvoiceLine whereItemId($value)
 * @method static Builder<static>|InvoiceLine whereItemServiceType($value)
 * @method static Builder<static>|InvoiceLine whereLineNum($value)
 * @method static Builder<static>|InvoiceLine whereLineTotal($value)
 * @method static Builder<static>|InvoiceLine wherePriceAfterDiscount($value)
 * @method static Builder<static>|InvoiceLine wherePriceBeforeDiscount($value)
 * @method static Builder<static>|InvoiceLine whereQtyInWarehouse($value)
 * @method static Builder<static>|InvoiceLine whereQuantity($value)
 * @method static Builder<static>|InvoiceLine whereUpdatedAt($value)
 * @method static Builder<static>|InvoiceLine whereVatAmount($value)
 * @method static Builder<static>|InvoiceLine whereVatCodeId($value)
 * @method static Builder<static>|InvoiceLine whereVatRate($value)
 * @method static Builder<static>|InvoiceLine whereWarehouseId($value)
 *
 * @mixin Eloquent
 */
class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'line_num', 'item_service_type', 'item_id',
        'item_description', 'warehouse_id', 'quantity', 'qty_in_warehouse',
        'price_before_discount', 'discount_percent', 'price_after_discount',
        'line_total', 'vat_code_id', 'vat_rate', 'vat_amount',
        'gross_price_after_discount', 'gross_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'price_before_discount' => 'decimal:4',
            'discount_percent' => 'decimal:6',
            'price_after_discount' => 'decimal:4',
            'line_total' => 'decimal:4',
            'qty_in_warehouse' => 'decimal:3',
            'vat_rate' => 'decimal:3',
            'vat_amount' => 'decimal:4',
            'gross_price_after_discount' => 'decimal:4',
            'gross_total' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function vatCode(): BelongsTo
    {
        return $this->belongsTo(VatCode::class);
    }

    /**
     * Where this line ships from. Nullable — a service line stocks nothing.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
