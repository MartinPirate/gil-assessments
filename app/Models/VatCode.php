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
 * @property numeric $rate
 * @property bool $is_active
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|VatCode newModelQuery()
 * @method static Builder<static>|VatCode newQuery()
 * @method static Builder<static>|VatCode query()
 * @method static Builder<static>|VatCode whereCode($value)
 * @method static Builder<static>|VatCode whereCreatedAt($value)
 * @method static Builder<static>|VatCode whereId($value)
 * @method static Builder<static>|VatCode whereIsActive($value)
 * @method static Builder<static>|VatCode whereIsDefault($value)
 * @method static Builder<static>|VatCode whereName($value)
 * @method static Builder<static>|VatCode whereRate($value)
 * @method static Builder<static>|VatCode whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class VatCode extends Model
{
    protected $fillable = ['code', 'name', 'rate', 'is_active', 'is_default'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:3',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {

        static::saved(function (self $vatCode): void {
            if (! $vatCode->is_default) {
                return;
            }

            static::query()
                ->whereKeyNot($vatCode->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    public static function default(): ?self
    {
        return static::query()->where('is_active', true)->where('is_default', true)->first();
    }
}
