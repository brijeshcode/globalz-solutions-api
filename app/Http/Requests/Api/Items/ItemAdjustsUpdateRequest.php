<?php

namespace App\Http\Requests\Api\Items;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class ItemAdjustsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::isAdmin();
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|required|date',
            'type' => 'sometimes|required|in:Add,Subtract',
            'warehouse_id' => 'sometimes|required|integer|exists:warehouses,id',
            'note' => 'nullable|string|max:1000',

            // Item adjust items for updates
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer|exists:item_adjust_items,id',
            'items.*.item_id' => 'required_with:items|integer|exists:items,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.0001|max:999999.9999',
            'items.*.note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Adjustment date is required.',
            'type.required' => 'Adjustment type is required.',
            'type.in' => 'Adjustment type must be either Add or Subtract.',
            'warehouse_id.required' => 'Warehouse is required.',
            'items.*.item_id.required_with' => 'Item is required for each adjustment item.',
            'items.*.quantity.required_with' => 'Quantity is required for each adjustment item.',
            'items.*.quantity.min' => 'Quantity must be greater than 0.',
            'items.required' => 'At least one item is required for the adjustment.',
            'items.min' => 'At least one item is required for the adjustment.',
        ];
    }
}
