<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $session_id
 * @property Carbon $logged_in_at
 * @property Carbon|null $logged_out_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static Builder<static>|LoginSession newModelQuery()
 * @method static Builder<static>|LoginSession newQuery()
 * @method static Builder<static>|LoginSession query()
 * @method static Builder<static>|LoginSession whereCreatedAt($value)
 * @method static Builder<static>|LoginSession whereId($value)
 * @method static Builder<static>|LoginSession whereIpAddress($value)
 * @method static Builder<static>|LoginSession whereLoggedInAt($value)
 * @method static Builder<static>|LoginSession whereLoggedOutAt($value)
 * @method static Builder<static>|LoginSession whereSessionId($value)
 * @method static Builder<static>|LoginSession whereUpdatedAt($value)
 * @method static Builder<static>|LoginSession whereUserAgent($value)
 * @method static Builder<static>|LoginSession whereUserId($value)
 *
 * @mixin Eloquent
 */
class LoginSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'session_id', 'logged_in_at', 'logged_out_at',
        'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'logged_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
