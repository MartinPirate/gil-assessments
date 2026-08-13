<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'line_num', 'item_service_type', 'item_id', 'item_no',
        'item_description', 'uom', 'warehouse', 'quantity', 'qty_in_warehouse',
        'price_before_discount', 'discount_percent', 'price_after_discount',
        'line_total', 'vat_code_id', 'vat_code', 'vat_rate', 'vat_amount',
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
}
