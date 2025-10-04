<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\RoleHelper;
use App\Models\Customers\Customer;
use App\Models\Customers\Sale;
use App\Models\Setups\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SaleOrdersUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|required|date',
            'prefix' => 'sometimes|required|in:INV,INX',
            'customer_id' => 'sometimes|required|exists:customers,id',
            'salesperson_id' => 'sometimes|nullable|exists:employees,id',
            'currency_id' => 'sometimes|required|exists:currencies,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'customer_payment_term_id' => 'sometimes|nullable|exists:customer_payment_terms,id',
            'client_po_number' => 'sometimes|nullable|string|max:255',
            'currency_rate' => 'sometimes|required|numeric|min:0',
            'credit_limit' => 'sometimes|nullable|numeric|min:0',
            'outStanding_balance' => 'sometimes|nullable|numeric|min:0',
            'sub_total' => 'sometimes|nullable|numeric|min:0',
            'sub_total_usd' => 'sometimes|nullable|numeric|min:0',
            'discount_amount' => 'sometimes|nullable|numeric|min:0',
            'discount_amount_usd' => 'sometimes|nullable|numeric|min:0',
            'total' => 'sometimes|required|numeric|min:0',
            'total_usd' => 'sometimes|required|numeric|min:0',
            'note' => 'sometimes|nullable|string',

            // Sale items - optional for updates
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'sometimes|nullable|exists:sale_items,id',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.supplier_id' => 'sometimes|nullable|exists:suppliers,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.price' => 'required_with:items|numeric|min:0',
            'items.*.ttc_price' => 'sometimes|nullable|numeric|min:0',
            'items.*.tax_percent' => 'sometimes|nullable|numeric|min:0',
            'items.*.discount_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'items.*.unit_discount_amount' => 'sometimes|nullable|numeric|min:0',
            'items.*.discount_amount' => 'sometimes|nullable|numeric|min:0',
            'items.*.total_price' => 'required_with:items|numeric|min:0',
            'items.*.total_price_usd' => 'sometimes|nullable|numeric|min:0',
            'items.*.note' => 'sometimes|nullable|string',
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
            'items.min' => 'At least one sale item is required when updating items',
            'items.*.item_id.required_with' => 'Item is required for all items',
            'items.*.quantity.required_with' => 'Quantity is required for all items',
            'items.*.quantity.min' => 'Quantity must be greater than 0',
            'items.*.price.required_with' => 'Price is required for all items',
            'items.*.price.min' => 'Price must be 0 or greater',
            'items.*.discount_percent.max' => 'Discount percentage cannot exceed 100%',
            'items.*.total_price.required_with' => 'Total price is required for all items',
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
            $sale = $this->route('sale');

            // Salesmen can only update their own sales
            if (RoleHelper::isSalesman()) {
                $employee = RoleHelper::getSalesmanEmployee();
                if ($this->input('salesperson_id') && $employee && $this->input('salesperson_id') != $employee->id) {
                    $validator->errors()->add('salesperson_id', 'You can only update your own sale orders');
                }
            }

            // Validate customer is active and belongs to salesperson
            if ($this->input('customer_id')) {
                $customer = Customer::find($this->input('customer_id'));
                if ($customer && !$customer->is_active) {
                    $validator->errors()->add('customer_id', 'Selected customer is inactive');
                }

                // Validate customer belongs to the salesperson
                $salespersonId = $this->input('salesperson_id');

                // If salesperson_id is not being updated, get it from the existing sale
                if (!$salespersonId && $sale) {
                    $existingSale = Sale::find($sale->id);
                    $salespersonId = $existingSale?->salesperson_id;
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

                    // Validate that item belongs to this sale if updating existing item
                    if (isset($item['id']) && $item['id'] && $sale) {
                        $existingItem = \App\Models\Customers\SaleItems::find($item['id']);
                        if ($existingItem && $existingItem->sale_id !== (int) $sale->id) {
                            $validator->errors()->add("items.{$index}.id", 'Item does not belong to this sale');
                        }
                    }
                }
            }
        });
    }
}
