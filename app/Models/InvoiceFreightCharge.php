<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One named charge making up a document's freight — delivery, insurance,
 * packing — with the VAT code that applies to it.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $line_num
 * @property string $description
 * @property numeric $amount
 * @property int|null $vat_code_id
 * @property numeric $vat_rate
 * @property numeric $vat_amount
 * @property string|null $remarks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read VatCode|null $vatCode
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereLineNum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereVatAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereVatCodeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceFreightCharge whereVatRate($value)
 *
 * @mixin \Eloquent
 */
class InvoiceFreightCharge extends Model
{
    protected $fillable = [
        'invoice_id', 'line_num', 'description', 'amount',
        'vat_code_id', 'vat_rate', 'vat_amount', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'vat_rate' => 'decimal:3',
            'vat_amount' => 'decimal:3',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function vatCode(): BelongsTo
    {
        return $this->belongsTo(VatCode::class);
    }
}
