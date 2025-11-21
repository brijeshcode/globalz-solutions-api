<?php

namespace Database\Factories\Items;

use App\Models\Items\Item;
use App\Models\Items\ItemTransfer;
use App\Models\Items\ItemTransferItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Items\ItemTransferItem>
 */
class ItemTransferItemFactory extends Factory
{
    protected $model = ItemTransferItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $item = Item::inRandomOrder()->first() ?? Item::factory()->create();

        return [
            'item_transfer_id' => ItemTransfer::factory(),
            'item_id' => $item->id,
            'item_code' => $item->code,
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'note' => $this->faker->optional(0.4)->sentence(),
            'created_by' => User::factory(),
            'updated_by' => function (array $attributes) {
                return $this->faker->boolean(30) ? User::factory() : $attributes['created_by'];
            },
        ];
    }

    /**
     * Item transfer item with a specific item
     */
    public function forItem(int $itemId): static
    {
        return $this->state(function (array $attributes) use ($itemId) {
            $item = Item::findOrFail($itemId);

            return [
                'item_id' => $item->id,
                'item_code' => $item->code,
            ];
        });
    }

    /**
     * Item transfer item with a specific transfer
     */
    public function forTransfer(int $itemTransferId): static
    {
        return $this->state(fn (array $attributes) => [
            'item_transfer_id' => $itemTransferId,
        ]);
    }

    /**
     * Small quantity transfer
     */
    public function smallQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $this->faker->randomFloat(2, 1, 10),
        ]);
    }

    /**
     * Large quantity transfer
     */
    public function largeQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $this->faker->randomFloat(2, 100, 1000),
        ]);
    }

    /**
     * Item transfer item with note
     */
    public function withNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => $this->faker->sentence(),
        ]);
    }

    /**
     * Item transfer item without note
     */
    public function withoutNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => null,
        ]);
    }
}
