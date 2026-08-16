<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $user_role
 * @property string $event
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string|null $auditable_label
 * @property array<array-key, mixed>|null $old_values
 * @property array<array-key, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $auditable
 * @property-read \App\Models\array<int, array{field: $changes_list
 * @property-read string $model_name
 * @property-read User|null $user
 *
 * @method static Builder<static>|AuditLog newModelQuery()
 * @method static Builder<static>|AuditLog newQuery()
 * @method static Builder<static>|AuditLog query()
 * @method static Builder<static>|AuditLog whereAuditableId($value)
 * @method static Builder<static>|AuditLog whereAuditableLabel($value)
 * @method static Builder<static>|AuditLog whereAuditableType($value)
 * @method static Builder<static>|AuditLog whereCreatedAt($value)
 * @method static Builder<static>|AuditLog whereEvent($value)
 * @method static Builder<static>|AuditLog whereId($value)
 * @method static Builder<static>|AuditLog whereIpAddress($value)
 * @method static Builder<static>|AuditLog whereNewValues($value)
 * @method static Builder<static>|AuditLog whereOldValues($value)
 * @method static Builder<static>|AuditLog whereUpdatedAt($value)
 * @method static Builder<static>|AuditLog whereUrl($value)
 * @method static Builder<static>|AuditLog whereUserAgent($value)
 * @method static Builder<static>|AuditLog whereUserId($value)
 * @method static Builder<static>|AuditLog whereUserName($value)
 * @method static Builder<static>|AuditLog whereUserRole($value)
 *
 * @mixin Eloquent
 */
class AuditLog extends Model
{
    public const string CREATED = 'created';

    public const string UPDATED = 'updated';

    public const string DELETED = 'deleted';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
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
