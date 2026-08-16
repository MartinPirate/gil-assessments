<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 *
 * @method static Builder<static>|SalesEmployee newModelQuery()
 * @method static Builder<static>|SalesEmployee newQuery()
 * @method static Builder<static>|SalesEmployee query()
 * @method static Builder<static>|SalesEmployee whereCode($value)
 * @method static Builder<static>|SalesEmployee whereCreatedAt($value)
 * @method static Builder<static>|SalesEmployee whereId($value)
 * @method static Builder<static>|SalesEmployee whereIsActive($value)
 * @method static Builder<static>|SalesEmployee whereName($value)
 * @method static Builder<static>|SalesEmployee wherePosition($value)
 * @method static Builder<static>|SalesEmployee whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SalesEmployee extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = ['code', 'name', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * The login belonging to this salesperson, where they have one.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
