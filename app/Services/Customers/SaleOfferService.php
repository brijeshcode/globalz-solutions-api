<?php

namespace App\Services\Customers;

use App\Models\Items\ItemOffer;
use Illuminate\Validation\ValidationException;

class SaleOfferService
{
    /**
     * Validate and normalize offer lines inside a sale / sale-order items array.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function normalize(array $items): array
    {
        // Group the indexes of every offer line by its offer id.
        $offerGroups = [];
        foreach ($items as $index => $item) {
            $offerId = $item['item_offer_id'] ?? null;
            if ($offerId) {
                $offerGroups[$offerId][] = $index;
            }
        }

        if (empty($offerGroups)) {
            return $items;
        }

        $errors = [];
        $offers = ItemOffer::whereIn('id', array_keys($offerGroups))->get()->keyBy('id');

        foreach ($offerGroups as $offerId => $indexes) {
            /** @var ItemOffer|null $offer */
            $offer = $offers->get($offerId);

            if (! $offer) {
                foreach ($indexes as $i) {
                    $errors["items.$i.item_offer_id"][] = 'Selected offer does not exist.';
                }
                continue;
            }

            if (! $offer->isAvailable()) {
                foreach ($indexes as $i) {
                    $errors["items.$i.item_offer_id"][] = 'This offer is not available (expired, inactive, or usage limit reached).';
                }
                continue;
            }

            $mainIndexes = array_values(array_filter($indexes, fn ($i) => ($items[$i]['offer_role'] ?? null) === 'main'));
            $freeIndexes = array_values(array_filter($indexes, fn ($i) => ($items[$i]['offer_role'] ?? null) === 'free'));

            if (empty($mainIndexes) || empty($freeIndexes)) {
                foreach ($indexes as $i) {
                    $errors["items.$i.offer_role"][] = 'An offer must include both a main and a free line.';
                }
                continue;
            }

            // One application only, unless the offer allows multiple.
            if (! $offer->allow_multiple && count($mainIndexes) > 1) {
                foreach ($mainIndexes as $i) {
                    $errors["items.$i.item_offer_id"][] = 'This offer can only be applied once per invoice.';
                }
                continue;
            }

            // Validate each main quantity and accumulate the expected free total.
            $expectedFree = 0;
            $mainValid = true;
            foreach ($mainIndexes as $i) {
                $qty = (int) ($items[$i]['quantity'] ?? 0);

                if ($offer->can_change_quantity) {
                    if ($qty < $offer->minimum_quantity || $qty % $offer->minimum_quantity !== 0) {
                        $errors["items.$i.quantity"][] = "Quantity must be a multiple of {$offer->minimum_quantity}.";
                        $mainValid = false;
                        continue;
                    }
                } elseif ($qty !== $offer->minimum_quantity) {
                    $errors["items.$i.quantity"][] = "Quantity must be exactly {$offer->minimum_quantity}.";
                    $mainValid = false;
                    continue;
                }

                $expectedFree += intdiv($qty, $offer->minimum_quantity) * $offer->free_quantity;
            }

            if (! $mainValid) {
                continue;
            }

            // Aggregate free quantity must match the expected total.
            $actualFree = 0;
            foreach ($freeIndexes as $i) {
                $actualFree += (int) ($items[$i]['quantity'] ?? 0);
            }

            if ($actualFree !== $expectedFree) {
                foreach ($freeIndexes as $i) {
                    $errors["items.$i.quantity"][] = "Free quantity must total {$expectedFree} for this offer.";
                }
                continue;
            }

            // Normalize: force 100% discount on every free line.
            foreach ($freeIndexes as $i) {
                $items[$i]['discount_percent'] = 100;
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $items;
    }
}
