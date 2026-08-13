<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'contact_person', 'currency', 'kra_pin', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
