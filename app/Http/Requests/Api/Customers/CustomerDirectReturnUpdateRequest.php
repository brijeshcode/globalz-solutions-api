<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\RoleHelper;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\CustomerReturnItem;
use App\Models\Setups\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CustomerDirectReturnUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|required|date',
            'prefix' => 'sometimes|required|in:RTX,RTN',
            'customer_id' => 'sometimes|required|exists:customers,id',
            'salesperson_id' => 'sometimes|nullable|exists:employees,id',
            'currency_id' => 'sometimes|required|exists:currencies,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'currency_rate' => 'required|numeric',
            'total' => 'sometimes|required|numeric|min:0',
            'total_usd' => 'sometimes|required|numeric|min:0',
            'total_volume_cbm' => 'sometimes|nullable|numeric|min:0',
            'total_weight_kg' => 'sometimes|nullable|numeric|min:0',
            'note' => 'sometimes|nullable|string',

            // Direct return items - optional for updates
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'sometimes|nullable|exists:customer_return_items,id',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.item_code' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.price' => 'required_with:items|numeric|min:0',
            'items.*.price_usd' => 'required_with:items|numeric|min:0',
            'items.*.discount_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'items.*.unit_discount_amount' => 'sometimes|nullable|numeric|min:0',
            'items.*.unit_discount_amount_usd' => 'sometimes|nullable|numeric|min:0',
            'items.*.tax_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'items.*.tax_label' => 'sometimes|nullable|string',
            'items.*.tax_amount' => 'sometimes|nullable|numeric|min:0',
            'items.*.tax_amount_usd' => 'sometimes|nullable|numeric|min:0',
            'items.*.ttc_price' => 'sometimes|nullable|numeric|min:0',
            'items.*.ttc_price_usd' => 'sometimes|nullable|numeric|min:0',
            'items.*.total_price' => 'required_with:items|numeric|min:0',
            'items.*.total_price_usd' => 'required_with:items|numeric|min:0',
            'items.*.unit_volume_cbm' => 'sometimes|nullable|numeric|min:0',
            'items.*.unit_weight_kg' => 'sometimes|nullable|numeric|min:0',
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
            'prefix.in' => 'Return prefix must be either RTX or RTN',
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
            'items.*.item_id.required_with' => 'Item is required for all items',
            'items.*.item_id.exists' => 'Selected item does not exist',
            'items.*.item_code.required_with' => 'Item code is required for all items',
            'items.*.quantity.required_with' => 'Quantity is required for all items',
            'items.*.quantity.min' => 'Quantity must be greater than 0',
            'items.*.price.required_with' => 'Price is required for all items',
            'items.*.price_usd.required_with' => 'Price in USD is required for all items',
            'items.*.total_price.required_with' => 'Total price is required for all items',
            'items.*.total_price_usd.required_with' => 'Total price in USD is required for all items',
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
            $customerReturn = $this->route('customerReturn');

            // Salesmen can only update their own returns
            $employee = RoleHelper::getSalesmanEmployee();
            if ($user->isSalesman() && $this->input('salesperson_id') && $this->input('salesperson_id') != $employee->id) {
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

            // Validate that item belongs to this return if updating existing item
            if ($this->input('items')) {
                foreach ($this->input('items') as $index => $item) {
                    if (isset($item['id']) && $item['id']) {
                        $existingItem = CustomerReturnItem::find($item['id']);
                        if ($existingItem && $existingItem->customer_return_id !== (int) $customerReturn->id) {
                            $validator->errors()->add("items.{$index}.id", 'Item does not belong to this return');
                        }
                    }
                }
            }
        });
    }
}
