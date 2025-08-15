<?php

namespace Database\Factories\Setups;

use App\Models\Setups\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setups\Country>
 */
class CountryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Country::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'iso2' => strtoupper(fake()->unique()->lexify('??')),
            'phone_code' => '+' . fake()->numberBetween(1, 999),
            'is_active' => fake()->boolean(90), // 90% chance of being active
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the country is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the country is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create United States.
     */
    public function usa(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'United States',
            'code' => 'USA',
            'iso2' => 'US',
            'phone_code' => '+1',
        ]);
    }

    /**
     * Create Canada.
     */
    public function canada(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Canada',
            'code' => 'CAN',
            'iso2' => 'CA',
            'phone_code' => '+1',
        ]);
    }

    /**
     * Create United Kingdom.
     */
    public function uk(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'United Kingdom',
            'code' => 'GBR',
            'iso2' => 'GB',
            'phone_code' => '+44',
        ]);
    }

    /**
     * Create Germany.
     */
    public function germany(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Germany',
            'code' => 'DEU',
            'iso2' => 'DE',
            'phone_code' => '+49',
        ]);
    }

    /**
     * Create India.
     */
    public function india(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'India',
            'code' => 'IND',
            'iso2' => 'IN',
            'phone_code' => '+91',
        ]);
    }

    /**
     * Create Japan.
     */
    public function japan(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Japan',
            'code' => 'JPN',
            'iso2' => 'JP',
            'phone_code' => '+81',
        ]);
    }

    /**
     * Create Australia.
     */
    public function australia(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Australia',
            'code' => 'AUS',
            'iso2' => 'AU',
            'phone_code' => '+61',
        ]);
    }

    /**
     * Create country without phone code.
     */
    public function withoutPhoneCode(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_code' => null,
        ]);
    }

    /**
     * Set specific phone code.
     */
    public function phoneCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_code' => $code,
        ]);
    }

    /**
     * Use existing users for created_by and updated_by.
     */
    public function withExistingUsers(): static
    {
        return $this->state(function (array $attributes) {
            $userIds = User::pluck('id')->toArray();
            
            if (empty($userIds)) {
                return $attributes;
            }

            return [
                'created_by' => fake()->randomElement($userIds),
                'updated_by' => fake()->randomElement($userIds),
            ];
        });
    }

    /**
     * Create a custom country.
     */
    public function custom(string $name, string $code, string $iso2, ?string $phoneCode = null): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'code' => strtoupper($code),
            'iso2' => strtoupper($iso2),
            'phone_code' => $phoneCode,
        ]);
    }
}