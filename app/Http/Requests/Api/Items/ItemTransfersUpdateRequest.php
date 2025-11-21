<?php

namespace App\Http\Requests\Api\Items;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class ItemTransfersUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::isAdmin();
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|required|date',
            'shipping_status' => 'nullable|in:Waiting,Shipped,Delivered',
            'from_warehouse_id' => 'sometimes|required|integer|exists:warehouses,id',
            'to_warehouse_id' => 'sometimes|required|integer|exists:warehouses,id|different:from_warehouse_id',
            'note' => 'nullable|string|max:1000',

            // Item transfer items for updates
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer|exists:item_transfer_items,id',
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
            'shipping_status.in' => 'Shipping status must be one of: Waiting, Shipped, Delivered.',
            'items.*.item_id.required_with' => 'Item is required for each transfer item.',
            'items.*.quantity.required_with' => 'Quantity is required for each transfer item.',
            'items.*.quantity.min' => 'Quantity must be greater than 0.',
            'items.required' => 'At least one item is required for the transfer.',
            'items.min' => 'At least one item is required for the transfer.',
        ];
    }
}
