<?php

namespace Database\Factories\Customers;

use App\Models\Customers\ProformaInvoice;
use App\Models\Items\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProformaInvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $price    = $this->faker->randomFloat(2, 10, 500);
        $quantity = $this->faker->numberBetween(1, 100);

        return [
            'proforma_invoice_id'      => ProformaInvoice::factory(),
            'item_id'                  => Item::factory(),
            'item_code'                => $this->faker->bothify('ITEM-###'),
            'quantity'                 => $quantity,
            'cost_price'               => $this->faker->randomFloat(2, 5, $price),
            'price'                    => $price,
            'price_usd'                => $price,
            'discount_percent'         => 0,
            'unit_discount_amount'     => 0,
            'unit_discount_amount_usd' => 0,
            'discount_amount'          => 0,
            'discount_amount_usd'      => 0,
            'net_sell_price'           => $price,
            'net_sell_price_usd'       => $price,
            'tax_percent'              => 0,
            'tax_label'                => 'No',
            'tax_amount'               => 0,
            'tax_amount_usd'           => 0,
            'total_tax_amount'         => 0,
            'total_tax_amount_usd'     => 0,
            'ttc_price'                => $price,
            'ttc_price_usd'            => $price,
            'total_net_sell_price'     => $price * $quantity,
            'total_net_sell_price_usd' => $price * $quantity,
            'total_price'              => $price * $quantity,
            'total_price_usd'          => $price * $quantity,
            'unit_profit'              => 0,
            'total_profit'             => 0,
            'unit_volume_cbm'          => 0,
            'unit_weight_kg'           => 0,
            'total_volume_cbm'         => 0,
            'total_weight_kg'          => 0,
            'created_by'               => User::factory(),
            'updated_by'               => User::factory(),
        ];
    }
}
