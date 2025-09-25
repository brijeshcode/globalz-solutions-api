<?php

namespace App\Http\Requests\Api\Customers;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Setups\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CustomerReturnOrdersUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|required|date',
            'prefix' => 'sometimes|required|in:RTX,RTV',
            'customer_id' => 'sometimes|required|exists:customers,id',
            'salesperson_id' => 'sometimes|nullable|exists:users,id',
            'currency_id' => 'sometimes|required|exists:currencies,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'currency_rate' => 'required|numeric',
            'total' => 'sometimes|required|numeric|min:0',
            'total_usd' => 'sometimes|required|numeric|min:0',
            'total_volume_cbm' => 'sometimes|nullable|numeric|min:0',
            'total_weight_kg' => 'sometimes|nullable|numeric|min:0',
            'note' => 'sometimes|nullable|string',

            // Return items - optional for updates
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'sometimes|nullable|exists:customer_return_items,id',
            'items.*.item_code' => 'required_with:items|string|max:255',
            'items.*.item_id' => 'sometimes|nullable|exists:items,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.001',
            'items.*.price' => 'required_with:items|numeric|min:0',
            'items.*.discount_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'required|numeric|min:0',
            'items.*.ttc_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.total_price_usd' => 'required|numeric|min:0',
            'items.*.unit_discount_amount' => 'sometimes|nullable|numeric|min:0',
            'items.*.tax_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'items.*.total_volume_cbm' => 'sometimes|nullable|numeric|min:0',
            'items.*.total_weight_kg' => 'sometimes|nullable|numeric|min:0',
            'items.*.note' => 'sometimes|nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Return date is required',
            'prefix.required' => 'Return prefix is required',
            'prefix.in' => 'Return prefix must be either RTX or RTV',
            'customer_id.required' => 'Customer is required',
            'customer_id.exists' => 'Selected customer does not exist',
            'currency_id.required' => 'Currency is required',
            'currency_id.exists' => 'Selected currency does not exist',
            'warehouse_id.required' => 'Warehouse is required',
            'warehouse_id.exists' => 'Selected warehouse does not exist',
            'total.required' => 'Total amount is required',
            'total.min' => 'Total amount must be 0 or greater',
            'total_usd.required' => 'Total amount in USD is required',
            'total_usd.min' => 'Total amount in USD must be 0 or greater',
            'items.min' => 'At least one return item is required when updating items',
            'items.*.item_code.required_with' => 'Item code is required for all items',
            'items.*.quantity.required_with' => 'Quantity is required for all items',
            'items.*.quantity.min' => 'Quantity must be greater than 0',
            'items.*.price.required_with' => 'Price is required for all items',
            'items.*.price.min' => 'Price must be 0 or greater',
            'items.*.discount_percent.max' => 'Discount percentage cannot exceed 100%',
            'items.*.tax_percent.max' => 'Tax percentage cannot exceed 100%',
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'currency_id' => 'currency',
            'warehouse_id' => 'warehouse',
            'salesperson_id' => 'salesperson',
            'total_usd' => 'total in USD',
            'total_volume_cbm' => 'total volume (CBM)',
            'total_weight_kg' => 'total weight (KG)',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $customerRetur = $this->route('customerReturn');
            // Salesmen can only update their own returns
            if ($user->isSalesman() && $this->input('salesperson_id') && $this->input('salesperson_id') != $user->id) {
                $validator->errors()->add('salesperson_id', 'You can only update your own returns');
            }

            // Validate customer is active and belongs to salesperson
            if ($this->input('customer_id')) {
                $customer = Customer::find($this->input('customer_id'));
                if ($customer && !$customer->is_active) {
                    $validator->errors()->add('customer_id', 'Selected customer is inactive');
                }

                // Validate customer belongs to the salesperson
                $salespersonId = $this->input('salesperson_id');

                // If salesperson_id is not being updated, get it from the existing return
                if (!$salespersonId && $this->route('customerReturn')) {
                    $existingReturn = CustomerReturn::find($this->route('customerReturn'));
                    $salespersonId = $existingReturn?->salesperson_id;
                }

                if ($customer && $salespersonId && $customer->salesperson_id !== (int) $salespersonId) {
                    $validator->errors()->add('customer_id', 'Selected customer does not belong to this salesperson');
                }
            }

            // Validate warehouse is active
            if ($this->input('warehouse_id')) {
                $warehouse = Warehouse::find($this->input('warehouse_id'));
                if ($warehouse && !$warehouse->is_active) {
                    $validator->errors()->add('warehouse_id', 'Selected warehouse is inactive');
                }
            }

            // Validate items exist and are active
            if ($this->input('items')) {
                foreach ($this->input('items') as $index => $item) {
                    if (isset($item['item_id']) && $item['item_id']) {
                        $itemModel = \App\Models\Items\Item::find($item['item_id']);
                        if (!$itemModel) {
                            $validator->errors()->add("items.{$index}.item_id", 'Selected item does not exist');
                        } elseif (!$itemModel->is_active) {
                            $validator->errors()->add("items.{$index}.item_id", 'Selected item is inactive');
                        }
                    }

                    // Validate that item belongs to this return if updating existing item
                    if (isset($item['id']) && $item['id']) {
                        $existingItem = \App\Models\Customers\CustomerReturnItem::find($item['id']);
                        if ($existingItem && $existingItem->customer_return_id !== (int) $customerRetur->id) {
                            $validator->errors()->add("items.{$index}.id", 'Item does not belong to this return');
                        }
                    }
                }
            }
        });
    }
}
