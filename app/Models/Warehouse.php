<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $location
 * @property bool $is_active
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|Warehouse newModelQuery()
 * @method static Builder<static>|Warehouse newQuery()
 * @method static Builder<static>|Warehouse query()
 * @method static Builder<static>|Warehouse whereCode($value)
 * @method static Builder<static>|Warehouse whereCreatedAt($value)
 * @method static Builder<static>|Warehouse whereId($value)
 * @method static Builder<static>|Warehouse whereIsActive($value)
 * @method static Builder<static>|Warehouse whereIsDefault($value)
 * @method static Builder<static>|Warehouse whereLocation($value)
 * @method static Builder<static>|Warehouse whereName($value)
 * @method static Builder<static>|Warehouse whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Warehouse extends Model
{
    protected $fillable = ['code', 'name', 'location', 'is_active', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public static function default(): ?self
    {
        return static::query()->where('is_active', true)->where('is_default', true)->first();
    }
}
