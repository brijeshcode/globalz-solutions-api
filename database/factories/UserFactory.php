<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            // Default to the most privileged role so factory users used as the
            // acting user pass every RoleHelper::canX() gate. Previously this was
            // fake()->randomElement([...all roles...]), which made every test that
            // hit a role-gated endpoint flaky (random 403s). Tests that need a
            // specific role should use the state helpers below.
            'role' => 'super_admin',
        ];
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

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'super_admin']);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'admin']);
    }

    public function salesman(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'salesman']);
    }

    public function warehouseManager(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'warehouse_manager']);
    }

    public function developer(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'developer']);
    }
}
