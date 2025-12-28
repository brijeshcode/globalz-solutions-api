<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\RoleHelper;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\SaleItems;
use App\Models\Setups\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CustomerReturnsUpdateRequest extends FormRequest
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
            'salesperson_id' => 'sometimes|nullable|exists:users,id',
            'currency_id' => 'sometimes|required|exists:currencies,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'currency_rate' => 'sometimes|required|numeric',
            'total' => 'sometimes|required|numeric|min:0',
            'total_usd' => 'sometimes|required|numeric|min:0',
            'total_volume_cbm' => 'sometimes|nullable|numeric|min:0',
            'total_weight_kg' => 'sometimes|nullable|numeric|min:0',
            'note' => 'sometimes|nullable|string',
            'approve_note' => 'sometimes|nullable|string',

            // Return items - optional for updates
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'sometimes|nullable|exists:customer_return_items,id',
            'items.*.sale_item_id' => 'required_with:items|exists:sale_items,id',
            'items.*.quantity' => 'required_with:items|numeric|min:1',
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
            'items.*.sale_item_id.required_with' => 'Sale item is required for all items',
            'items.*.sale_item_id.exists' => 'Selected sale item does not exist',
            'items.*.quantity.required_with' => 'Quantity is required for all items',
            'items.*.quantity.min' => 'Quantity must be greater than 0',
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

            // Validate sale items exist and belong to valid sales
            if ($this->input('items')) {
                foreach ($this->input('items') as $index => $item) {
                    if (isset($item['sale_item_id']) && $item['sale_item_id']) {
                        $saleItem = SaleItems::with('sale')->find($item['sale_item_id']);
                        if (!$saleItem) {
                            $validator->errors()->add("items.{$index}.sale_item_id", 'Selected sale item does not exist');
                        } else {
                            // Validate sale item belongs to the selected customer
                            if ($this->input('customer_id') && $saleItem->sale && $saleItem->sale->customer_id !== (int) $this->input('customer_id')) {
                                $validator->errors()->add("items.{$index}.sale_item_id", 'Sale item does not belong to the selected customer');
                            }

                            // Check existing returns for this sale item (excluding current item being updated)
                            $existingReturnsQuery = \App\Models\Customers\CustomerReturnItem::where('sale_item_id', $item['sale_item_id'])
                                ->whereHas('customerReturn', function ($query) {
                                    // Include both pending (approved_by is null) and approved (approved_by is not null) returns
                                    $query->whereNull('deleted_at');
                                });

                            // Exclude current item if it's being updated
                            if (isset($item['id']) && $item['id']) {
                                $existingReturnsQuery->where('id', '!=', $item['id']);
                            }

                            $existingReturns = $existingReturnsQuery->sum('quantity');
                            $requestedQuantity = $item['quantity'] ?? 0;
                            $totalReturnQuantity = $existingReturns + $requestedQuantity;

                            // Validate total return quantity doesn't exceed original sale quantity
                            if ($totalReturnQuantity > $saleItem->quantity) {
                                $availableQuantity = $saleItem->quantity - $existingReturns;
                                $validator->errors()->add(
                                    "items.{$index}.quantity",
                                    "Return quantity cannot exceed available quantity. Original: {$saleItem->quantity}, Already returned/pending: {$existingReturns}, Available: {$availableQuantity}"
                                );
                            }
                        }
                    }

                    // Validate that item belongs to this return if updating existing item
                    if (isset($item['id']) && $item['id']) {
                        $customerReturn = $this->route('customerReturn');
                        $existingItem = \App\Models\Customers\CustomerReturnItem::find($item['id']);
                        if ($existingItem && $customerReturn && $existingItem->customer_return_id !== (int) $customerReturn->id) {
                            $validator->errors()->add("items.{$index}.id", 'Item does not belong to this return');
                        }
                    }
                }
            }
        });
    }
}
