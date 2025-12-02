<?php

namespace App\Http\Requests\Api\Customers;

use App\Models\Customers\Customer;
use App\Models\Setups\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CustomerReturnsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return $user->isAdmin();
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'prefix' => 'required|in:RTX,RTN',
            'customer_id' => 'required|exists:customers,id',
            'salesperson_id' => 'nullable|exists:users,id',
            'currency_id' => 'required|exists:currencies,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'currency_rate' => 'required|numeric',
            'total' => 'required|numeric|min:0',
            'total_usd' => 'required|numeric|min:0',
            'total_volume_cbm' => 'nullable|numeric|min:0|default:0',
            'total_weight_kg' => 'nullable|numeric|min:0|default:0',
            'note' => 'nullable|string',
            'approve_note' => 'nullable|string',

            // Return items
            'items' => 'required|array|min:1',
            'items.*.item_code' => 'required|string|max:255',
            'items.*.item_id' => 'nullable|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.unit_discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.total_volume_cbm' => 'nullable|numeric|min:0',
            'items.*.total_weight_kg' => 'nullable|numeric|min:0',
            'items.*.note' => 'nullable|string',
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
            'items.required' => 'At least one return item is required',
            'items.min' => 'At least one return item is required',
            'items.*.item_code.required' => 'Item code is required for all items',
            'items.*.quantity.required' => 'Quantity is required for all items',
            'items.*.quantity.min' => 'Quantity must be greater than 0',
            'items.*.price.required' => 'Price is required for all items',
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
            // Validate customer is active
            if ($this->input('customer_id')) {
                $customer = Customer::find($this->input('customer_id'));
                if ($customer && !$customer->is_active) {
                    $validator->errors()->add('customer_id', 'Selected customer is inactive');
                }

                // Validate customer belongs to the salesperson
                if ($customer && $this->input('salesperson_id') && $customer->salesperson_id !== (int) $this->input('salesperson_id')) {
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
                        $itemModel = \App\Models\Items\Item::select('id', 'is_active')->find($item['item_id']);
                        if (!$itemModel) {
                            $validator->errors()->add("items.{$index}.item_id", 'Selected item does not exist');
                        } elseif (!$itemModel->is_active) {
                            $validator->errors()->add("items.{$index}.item_id", 'Selected item is inactive');
                        }
                    }
                }
            }
        });
    }
}
