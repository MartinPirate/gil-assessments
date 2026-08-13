<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
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
            'role' => UserRole::Admin,
            'is_active' => true,
            'approval_limit' => null,
        ];
    }

    /**
     * Role states, so a test can be explicit about what the actor may do
     * rather than relying on the default.
     */
    public function role(UserRole $role, ?float $approvalLimit = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
            'approval_limit' => $approvalLimit,
        ]);
    }

    public function sales(): static
    {
        return $this->role(UserRole::Sales);
    }

    public function approver(?float $limit = null): static
    {
        return $this->role(UserRole::Approver, $limit);
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
