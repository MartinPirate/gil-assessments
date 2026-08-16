<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Every driver has a login, so one is made unless the caller
            // passes their own user_id.
            'user_id' => User::factory()->role(UserRole::Driver),
            'name' => fake()->name(),
            'national_id' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'phone' => '07'.fake()->unique()->numerify('########'),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Driver $driver): void {
            // The driver and their account are one person, so the account
            // answers to the same name — including when the caller named the
            // driver and left the user to the factory.
            $driver->user()->first()?->update(['name' => $driver->name]);
        });
    }
}
