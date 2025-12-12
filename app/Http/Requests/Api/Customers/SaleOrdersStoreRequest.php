<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\RoleHelper;
use App\Models\Customers\Customer;
use App\Models\Setups\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SaleOrdersStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'prefix' => 'required|in:INV,INX',
            'customer_id' => 'required|exists:customers,id',
            'salesperson_id' => 'nullable|exists:employees,id',
            'currency_id' => 'required|exists:currencies,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'customer_payment_term_id' => 'nullable|exists:customer_payment_terms,id',
            'client_po_number' => 'nullable|string|max:255',
            'currency_rate' => 'required|numeric|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'outStanding_balance' => 'nullable|numeric|min:0',
            'sub_total' => 'nullable|numeric|min:0',
            'sub_total_usd' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_amount_usd' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'total_usd' => 'required|numeric|min:0',
            'note' => 'nullable|string',

            // Sale items
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.supplier_id' => 'nullable|exists:suppliers,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.ttc_price' => 'nullable|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.unit_discount_amount' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.total_price_usd' => 'nullable|numeric|min:0',
            'items.*.note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Sale date is required',
            'prefix.required' => 'Sale prefix is required',
            'prefix.in' => 'Sale prefix must be either INV or INX',
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
            'items.required' => 'At least one sale item is required',
            'items.min' => 'At least one sale item is required',
            'items.*.item_id.required' => 'Item is required for all items',
            'items.*.quantity.required' => 'Quantity is required for all items',
            'items.*.quantity.min' => 'Quantity must be greater than 0',
            'items.*.price.required' => 'Price is required for all items',
            'items.*.price.min' => 'Price must be 0 or greater',
            'items.*.discount_percent.max' => 'Discount percentage cannot exceed 100%',
            'items.*.total_price.required' => 'Total price is required for all items',
            'items.*.total_price.min' => 'Total price must be 0 or greater',
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
            'client_po_number' => 'client PO number',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // If salesperson_id is not provided, set it to current user if they are a salesman
            if (RoleHelper::isSalesman()) {
                $employee = RoleHelper::getSalesmanEmployee();
                if ($employee) {
                    $this->merge(['salesperson_id' => $employee->id]);
                }
            }

            // Salesmen can only create sale orders for themselves
            if (RoleHelper::isSalesman()) {
                $employee = RoleHelper::getSalesmanEmployee();
                if ($this->input('salesperson_id') && $employee && $this->input('salesperson_id') != $employee->id) {
                    $validator->errors()->add('salesperson_id', 'You can only create sale orders for yourself');
                }
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
                }
            }
        });
    }

    protected function prepareForValidation()
    {
        // Auto-set salesperson for salesmen
        if (RoleHelper::isSalesman() && !$this->salesperson_id) {
            $employee = RoleHelper::getSalesmanEmployee();
            if ($employee) {
                $this->merge(['salesperson_id' => $employee->id]);
            }
        }
    }
}
