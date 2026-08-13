<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';

    protected $fillable = [
        'user_id', 'user_name', 'user_role', 'event',
        'auditable_type', 'auditable_id', 'auditable_label',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'url',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * "Invoice", not "App\Models\Invoice" — the class path means nothing to
     * whoever is reading the log.
     */
    public function getModelNameAttribute(): string
    {
        return class_basename($this->auditable_type);
    }

    /**
     * The fields that actually changed, for the diff view.
     *
     * @return array<int, array{field: string, from: mixed, to: mixed}>
     */
    public function getChangesListAttribute(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        return collect(array_keys($old + $new))
            ->map(fn (string $field) => [
                'field' => $field,
                'from' => $old[$field] ?? null,
                'to' => $new[$field] ?? null,
            ])
            ->values()
            ->all();
    }
}
