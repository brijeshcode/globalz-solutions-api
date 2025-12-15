<?php

namespace App\Http\Requests\Api\Items;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class ItemTransfersStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'from_warehouse_id' => 'required|integer|exists:warehouses,id',
            'to_warehouse_id' => 'required|integer|exists:warehouses,id|different:from_warehouse_id',
            'note' => 'nullable|string|max:1000',

            // Item transfer items
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required_with:items|integer|exists:items,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.0001|max:999999.9999',
            'items.*.note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Transfer date is required.',
            'from_warehouse_id.required' => 'Source warehouse is required.',
            'to_warehouse_id.required' => 'Destination warehouse is required.',
            'to_warehouse_id.different' => 'Destination warehouse must be different from source warehouse.',
            'items.*.item_id.required_with' => 'Item is required for each transfer item.',
            'items.*.quantity.required_with' => 'Quantity is required for each transfer item.',
            'items.*.quantity.min' => 'Quantity must be greater than 0.',
            'items.required' => 'At least one item is required for the transfer.',
            'items.min' => 'At least one item is required for the transfer.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default shipping status if not provided
        $this->merge([
        ]);
    }
}
