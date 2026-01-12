<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\RoleHelper;
use App\Models\Customers\Customer;
use App\Models\Setups\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CustomerDirectReturnStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'prefix' => 'required|in:RTX,RTN',
            'customer_id' => 'required|exists:customers,id',
            'salesperson_id' => 'nullable|exists:employees,id',
            'currency_id' => 'required|exists:currencies,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'total' => 'required|numeric|min:0',
            'total_usd' => 'required|numeric|min:0',
            'currency_rate' => 'required|numeric',
            'total_volume_cbm' => 'nullable|numeric|min:0',
            'total_weight_kg' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',

            // Direct return items (no sale_item_id required)
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.item_code' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.price_usd' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.unit_discount_amount' => 'nullable|numeric|min:0',
            'items.*.unit_discount_amount_usd' => 'nullable|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_label' => 'nullable|string',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.tax_amount_usd' => 'nullable|numeric|min:0',
            'items.*.ttc_price' => 'nullable|numeric|min:0',
            'items.*.ttc_price_usd' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.total_price_usd' => 'required|numeric|min:0',
            'items.*.unit_volume_cbm' => 'nullable|numeric|min:0',
            'items.*.unit_weight_kg' => 'nullable|numeric|min:0',
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
            'items.required' => 'At least one return item is required',
            'items.min' => 'At least one return item is required',
            'items.*.item_id.required' => 'Item is required for all items',
            'items.*.item_id.exists' => 'Selected item does not exist',
            'items.*.item_code.required' => 'Item code is required for all items',
            'items.*.quantity.required' => 'Quantity is required for all items',
            'items.*.quantity.min' => 'Quantity must be greater than 0',
            'items.*.price.required' => 'Price is required for all items',
            'items.*.price_usd.required' => 'Price in USD is required for all items',
            'items.*.total_price.required' => 'Total price is required for all items',
            'items.*.total_price_usd.required' => 'Total price in USD is required for all items',
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
            
            if(RoleHelper::isSalesman()){
                $salePersonEmployee = RoleHelper::getSalesmanEmployee();
                $this->merge(['salesperson_id' => $salePersonEmployee->id]);
            }

            // Validate customer is active and belongs to salesperson
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
        });
    }

    protected function prepareForValidation()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Auto-set salesperson for salesmen
        if ($user->isSalesman() && !$this->salesperson_id) {
            $this->merge(['salesperson_id' => $user->id]);
        }
    }
}
