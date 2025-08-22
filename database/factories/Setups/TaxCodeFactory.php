<?php

namespace Database\Factories\Setups;

use App\Models\Setups\TaxCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxCodeFactory extends Factory
{
    protected $model = TaxCode::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->regexify('[A-Z]{3,6}'),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'tax_percent' => $this->faker->randomFloat(2, 0, 25), // 0% to 25%
            'type' => $this->faker->randomElement(['inclusive', 'exclusive']),
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
            'is_default' => false, // Will be set explicitly when needed
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Create a VAT tax code.
     */
    public function vat(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'VAT',
            'name' => 'Value Added Tax',
            'description' => 'Standard VAT rate',
            'tax_percent' => 15.00,
            'type' => 'exclusive',
        ]);
    }

    /**
     * Create a no tax code.
     */
    public function noTax(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'NOTAX',
            'name' => 'No Tax',
            'description' => 'Tax exempt items',
            'tax_percent' => 0.00,
            'type' => 'exclusive',
        ]);
    }

    /**
     * Create an inclusive tax code.
     */
    public function inclusive(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'inclusive',
        ]);
    }

    /**
     * Create an exclusive tax code.
     */
    public function exclusive(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'exclusive',
        ]);
    }

    /**
     * Create an active tax code.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive tax code.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a default tax code.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Create a high tax rate.
     */
    public function highTax(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_percent' => $this->faker->randomFloat(2, 20, 35),
        ]);
    }

    /**
     * Create a low tax rate.
     */
    public function lowTax(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_percent' => $this->faker->randomFloat(2, 1, 10),
        ]);
    }

    /**
     * Create with specific tax percentage.
     */
    public function withTaxPercent(float $percent): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_percent' => $percent,
        ]);
    }

    /**
     * Create with specific code.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => strtoupper($code),
        ]);
    }
}