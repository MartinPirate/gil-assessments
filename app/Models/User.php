<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\Auditable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use Auditable;
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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
            'role' => UserRole::class,
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

    public function driver(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * The driver record this login belongs to, if any. The driver portal scopes
     * everything by this, so a null here must show nothing rather than all.
     */
    public function driverId(): ?int
    {
        return $this->driver?->getKey();
    }

    public function role(): UserRole
    {
        return $this->role ?? UserRole::Sales;
    }

    /**
     * Whether this user may decide an approval request of a given amount.
     *
     * A null limit means unlimited; anything else is a ceiling, so a junior
     * approver cannot wave through a document above their authority.
     */
    public function canApproveAmount(float $amount): bool
    {
        if (! $this->role()->canApprove()) {
            return false;
        }

        return $this->approval_limit === null || $amount <= (float) $this->approval_limit;
    }
}
