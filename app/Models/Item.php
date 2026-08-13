<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'item_no', 'description', 'uom', 'warehouse',
        'unit_price', 'qty_in_warehouse', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'qty_in_warehouse' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }
}
