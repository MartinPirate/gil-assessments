<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $item_no
 * @property string $description
 * @property string $uom
 * @property numeric $unit_price
 * @property numeric $qty_in_warehouse
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $warehouse_id
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read Warehouse $warehouse
 *
 * @method static Builder<static>|Item newModelQuery()
 * @method static Builder<static>|Item newQuery()
 * @method static Builder<static>|Item query()
 * @method static Builder<static>|Item whereCreatedAt($value)
 * @method static Builder<static>|Item whereDescription($value)
 * @method static Builder<static>|Item whereId($value)
 * @method static Builder<static>|Item whereIsActive($value)
 * @method static Builder<static>|Item whereItemNo($value)
 * @method static Builder<static>|Item whereQtyInWarehouse($value)
 * @method static Builder<static>|Item whereUnitPrice($value)
 * @method static Builder<static>|Item whereUom($value)
 * @method static Builder<static>|Item whereUpdatedAt($value)
 * @method static Builder<static>|Item whereWarehouseId($value)
 *
 * @mixin Eloquent
 */
class Item extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'item_no', 'description', 'uom', 'warehouse_id',
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

    /**
     * Where this item is stocked by default.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
