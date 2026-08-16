<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Laratrust\Models\Role as RoleModel;

/**
 * @property int $id
 * @property string $name
 * @property string|null $display_name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 *
 * @method static Builder<static>|Role newModelQuery()
 * @method static Builder<static>|Role newQuery()
 * @method static Builder<static>|Role query()
 * @method static Builder<static>|Role whereCreatedAt($value)
 * @method static Builder<static>|Role whereDescription($value)
 * @method static Builder<static>|Role whereDisplayName($value)
 * @method static Builder<static>|Role whereId($value)
 * @method static Builder<static>|Role whereName($value)
 * @method static Builder<static>|Role whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Role extends RoleModel
{
    public $guarded = [];
}
