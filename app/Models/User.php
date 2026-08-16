<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\MirrorsLinkedName;
use Database\Factories\UserFactory;
use Eloquent;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laratrust\Contracts\LaratrustUser;
use Laratrust\Traits\HasRolesAndPermissions;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $is_active
 * @property numeric|null $approval_limit
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read Driver|null $driver
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User orWhereHasPermission(\BackedEnum|array|string $permission = '', ?mixed $team = null)
 * @method static Builder<static>|User orWhereHasRole(\BackedEnum|array|string $role = '', ?mixed $team = null)
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereApprovalLimit($value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereDoesntHavePermissions()
 * @method static Builder<static>|User whereDoesntHaveRoles()
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereHasPermission(\BackedEnum|array|string $permission = '', ?mixed $team = null, string $boolean = 'and')
 * @method static Builder<static>|User whereHasRole(\BackedEnum|array|string $role = '', ?mixed $team = null, string $boolean = 'and')
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereIsActive($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class User extends Authenticatable implements FilamentUser, LaratrustUser
{
    use Auditable;
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasRolesAndPermissions;

    use MirrorsLinkedName;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'approval_limit',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'approval_limit' => 'decimal:3',
        ];
    }

    /**
     * Deactivated accounts keep their history but cannot sign in.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * The sales employee record this login belongs to, if any. Documents are
     * attributed to the employee, so this is what "my orders" means.
     */
    public function salesEmployee(): HasOne
    {
        return $this->hasOne(SalesEmployee::class);
    }

    public function salesEmployeeId(): ?int
    {
        return $this->salesEmployee?->getKey();
    }

    public function linkedNameRecord(): ?Model
    {
        return $this->driver()->first();
    }

    /**
     * The driver record this login belongs to, if any. The driver portal scopes
     * everything by this, so a null here must show nothing rather than all.
     */
    public function driverId(): ?int
    {
        return $this->driver?->getKey();
    }

    /**
     * The one role this account holds.
     *
     * Laratrust allows many; this application gives out exactly one, so the
     * first is the answer. Used for labels and for the role picker — never for
     * deciding what someone may do, which is what the permissions below are
     * for.
     */
    public function role(): ?UserRole
    {
        $name = $this->roles->first()?->name;

        return $name ? UserRole::tryFrom($name) : null;
    }

    public function roleLabel(): string
    {
        return $this->role()?->label() ?? 'No role';
    }

    /* -----------------------------------------------------------------
     | Capabilities
     |
     | Each asks Laratrust about a permission rather than comparing a role, so
     | granting one to another role is a change to UserRole::permissions() and
     | nothing else.
     | ----------------------------------------------------------------- */

    public function canSell(): bool
    {
        return $this->isAbleTo(Permission::SellDocuments->value);
    }

    public function canApprove(): bool
    {
        return $this->isAbleTo(Permission::ApproveDocuments->value);
    }

    public function canOperateGate(): bool
    {
        return $this->isAbleTo(Permission::OperateGate->value);
    }

    public function canAdminister(): bool
    {
        return $this->isAbleTo(Permission::AdministerSystem->value);
    }

    public function canViewPayments(): bool
    {
        return $this->isAbleTo(Permission::ViewPayments->value);
    }

    public function canManageTrips(): bool
    {
        return $this->isAbleTo(Permission::ManageTrips->value);
    }

    public function canViewAuditLog(): bool
    {
        return $this->isAbleTo(Permission::ViewAuditLog->value);
    }

    /**
     * A driver signs in only to see their own work. Deliberately narrow: this
     * must not reach invoices, payments or another driver's trips.
     */
    public function isDriver(): bool
    {
        return $this->isAbleTo(Permission::Drive->value);
    }

    /**
     * Whether this user may decide an approval request of a given amount.
     *
     * A null limit means unlimited; anything else is a ceiling, so a junior
     * approver cannot wave through a document above their authority.
     */
    public function canApproveAmount(float $amount): bool
    {
        if (! $this->canApprove()) {
            return false;
        }

        return $this->approval_limit === null || $amount <= (float) $this->approval_limit;
    }
}
