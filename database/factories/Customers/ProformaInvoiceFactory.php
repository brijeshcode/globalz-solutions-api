<?php

namespace Database\Factories\Customers;

use App\Models\Customers\Customer;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProformaInvoiceFactory extends Factory
{
    public function definition(): array
    {
        $subTotal     = $this->faker->numberBetween(100, 5000);
        $discount     = $this->faker->numberBetween(0, (int)($subTotal * 0.1));
        $total        = $subTotal - $discount;
        $currencyRate = $this->faker->randomFloat(4, 0.5, 2.0);

        return [
            'code'                     => $this->faker->unique()->numerify('######'),
            'date'                     => $this->faker->dateTimeBetween('-1 year', 'now'),
            'prefix'                   => $this->faker->randomElement(['PINV', 'PINX']),
            'status'                   => 'Draft',
            'salesperson_id'           => null,
            'customer_id'              => Customer::factory(),
            'currency_id'              => Currency::factory(),
            'warehouse_id'             => Warehouse::factory(),
            'customer_payment_term_id' => null,
            'client_po_number'         => $this->faker->optional()->bothify('PO-####-????'),
            'currency_rate'            => $currencyRate,
            'sub_total'                => $subTotal,
            'sub_total_usd'            => round($subTotal / $currencyRate, 2),
            'discount_amount'          => $discount,
            'discount_amount_usd'      => round($discount / $currencyRate, 2),
            'total'                    => $total,
            'total_usd'                => round($total / $currencyRate, 2),
            'note'                     => $this->faker->optional()->sentence(),
            'created_by'               => User::factory(),
            'updated_by'               => User::factory(),
            'converted_at'             => null,
            'converted_sale_id'        => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn(array $a) => ['status' => 'Accepted']);
    }

    public function converted(): static
    {
        return $this->state(fn(array $a) => [
            'status'       => 'Converted',
            'converted_at' => now(),
        ]);
    }

    public function pinv(): static
    {
        return $this->state(fn(array $a) => ['prefix' => 'PINV']);
    }

    public function pinx(): static
    {
        return $this->state(fn(array $a) => ['prefix' => 'PINX']);
    }
}
