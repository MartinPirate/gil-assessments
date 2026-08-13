<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
