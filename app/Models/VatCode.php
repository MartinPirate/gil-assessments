<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public static function default(): ?self
    {
        return static::query()->where('is_active', true)->where('is_default', true)->first();
    }
}
