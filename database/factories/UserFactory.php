<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
            'approval_limit' => null,
        ];
    }

    /**
     * Every user gets a role, and unless a test says otherwise it is the
     * administrator — the same default the factory had when the role was a
     * column, so existing tests keep the actor they were written against.
     *
     * role() below re-syncs, so a stated role replaces this rather than
     * stacking on top of it.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            AccessControl::sync();

            if ($user->roles()->count() === 0) {
                $user->syncRoles([UserRole::Admin->value]);
                $user->load('roles.permissions');
            }
        });
    }

    /**
     * Role states, so a test can be explicit about what the actor may do
     * rather than relying on the default.
     *
     * The role is a Laratrust row, not a column, so it is attached after the
     * user exists. Provisioning is done here too: a test that builds a user
     * without seeding the matrix would otherwise get an account holding a role
     * that grants nothing.
     */
    public function role(UserRole $role, ?float $approvalLimit = null): static
    {
        return $this
            ->state(fn (array $attributes) => ['approval_limit' => $approvalLimit])
            ->afterCreating(function (User $user) use ($role): void {
                AccessControl::sync();

                $user->syncRoles([$role->value]);
                $user->load('roles.permissions');
            });
    }

    public function admin(): static
    {
        return $this->role(UserRole::Admin);
    }

    public function sales(): static
    {
        return $this->role(UserRole::Sales);
    }

    public function manager(?float $limit = null): static
    {
        return $this->role(UserRole::Manager, $limit);
    }

    public function gateOfficer(): static
    {
        return $this->role(UserRole::GateOfficer);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
