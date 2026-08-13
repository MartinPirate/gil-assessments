<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'national_id', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trips(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Trip::class)->latest('scheduled_at');
    }

    public function gateLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GateLog::class)->latest('time_in');
    }
}
