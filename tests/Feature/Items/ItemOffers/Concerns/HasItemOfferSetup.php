<?php

namespace Tests\Feature\Items\ItemOffers\Concerns;

use App\Models\Items\Item;
use App\Models\User;

trait HasItemOfferSetup
{
    protected User $admin;
    protected User $salesman;
    protected Item $item;
    protected Item $freeItem;

    public function setUpItemOffers(): void
    {
        $this->admin    = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->salesman = User::factory()->salesman()->create();
        $this->actingAs($this->admin, 'sanctum');

        $this->item     = Item::factory()->create();
        $this->freeItem = Item::factory()->create();
    }

    protected function offerPayload(array $overrides = []): array
    {
        return array_merge([
            'item_id'             => $this->item->id,
            'free_item_id'        => $this->freeItem->id,
            'date'                => '2026-01-01',
            'validity_date'       => '2026-12-31',
            'minimum_quantity'    => 5,
            'free_quantity'       => 1,
            'usage_limit'         => 100,
            'can_change_quantity' => false,
            'allow_multiple'      => false,
            'is_active'           => true,
        ], $overrides);
    }
}
