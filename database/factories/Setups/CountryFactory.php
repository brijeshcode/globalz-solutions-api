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
        $countries = [
            ['name' => 'United States', 'code' => 'USA', 'iso2' => 'US', 'phone_code' => '+1', 'currency' => 'USD'],
            ['name' => 'Canada', 'code' => 'CAN', 'iso2' => 'CA', 'phone_code' => '+1', 'currency' => 'CAD'],
            ['name' => 'United Kingdom', 'code' => 'GBR', 'iso2' => 'GB', 'phone_code' => '+44', 'currency' => 'GBP'],
            ['name' => 'Germany', 'code' => 'DEU', 'iso2' => 'DE', 'phone_code' => '+49', 'currency' => 'EUR'],
            ['name' => 'France', 'code' => 'FRA', 'iso2' => 'FR', 'phone_code' => '+33', 'currency' => 'EUR'],
            ['name' => 'Japan', 'code' => 'JPN', 'iso2' => 'JP', 'phone_code' => '+81', 'currency' => 'JPY'],
            ['name' => 'Australia', 'code' => 'AUS', 'iso2' => 'AU', 'phone_code' => '+61', 'currency' => 'AUD'],
            ['name' => 'India', 'code' => 'IND', 'iso2' => 'IN', 'phone_code' => '+91', 'currency' => 'INR'],
            ['name' => 'China', 'code' => 'CHN', 'iso2' => 'CN', 'phone_code' => '+86', 'currency' => 'CNY'],
            ['name' => 'Brazil', 'code' => 'BRA', 'iso2' => 'BR', 'phone_code' => '+55', 'currency' => 'BRL'],
            ['name' => 'Mexico', 'code' => 'MEX', 'iso2' => 'MX', 'phone_code' => '+52', 'currency' => 'MXN'],
            ['name' => 'South Africa', 'code' => 'ZAF', 'iso2' => 'ZA', 'phone_code' => '+27', 'currency' => 'ZAR'],
            ['name' => 'Italy', 'code' => 'ITA', 'iso2' => 'IT', 'phone_code' => '+39', 'currency' => 'EUR'],
            ['name' => 'Spain', 'code' => 'ESP', 'iso2' => 'ES', 'phone_code' => '+34', 'currency' => 'EUR'],
            ['name' => 'Netherlands', 'code' => 'NLD', 'iso2' => 'NL', 'phone_code' => '+31', 'currency' => 'EUR'],
            ['name' => 'Switzerland', 'code' => 'CHE', 'iso2' => 'CH', 'phone_code' => '+41', 'currency' => 'CHF'],
            ['name' => 'Sweden', 'code' => 'SWE', 'iso2' => 'SE', 'phone_code' => '+46', 'currency' => 'SEK'],
            ['name' => 'Norway', 'code' => 'NOR', 'iso2' => 'NO', 'phone_code' => '+47', 'currency' => 'NOK'],
            ['name' => 'Denmark', 'code' => 'DNK', 'iso2' => 'DK', 'phone_code' => '+45', 'currency' => 'DKK'],
            ['name' => 'Russia', 'code' => 'RUS', 'iso2' => 'RU', 'phone_code' => '+7', 'currency' => 'RUB'],
        ];

        $country = fake()->randomElement($countries);

        return [
            'name' => $country['name'],
            'code' => $country['code'],
            'iso2' => $country['iso2'],
            'phone_code' => $country['phone_code'],
            'currency' => $country['currency'],
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
            'currency' => 'USD',
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
            'currency' => 'CAD',
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
            'currency' => 'GBP',
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
            'currency' => 'EUR',
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
            'currency' => 'INR',
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
            'currency' => 'JPY',
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
            'currency' => 'AUD',
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
     * Create country without currency.
     */
    public function withoutCurrency(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => null,
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
     * Set specific currency.
     */
    public function currency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
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
    public function custom(string $name, string $code, string $iso2, ?string $phoneCode = null, ?string $currency = null): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'code' => strtoupper($code),
            'iso2' => strtoupper($iso2),
            'phone_code' => $phoneCode,
            'currency' => $currency ? strtoupper($currency) : null,
        ]);
    }
}