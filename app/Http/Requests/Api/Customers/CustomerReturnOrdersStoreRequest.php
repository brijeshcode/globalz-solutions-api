<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\ApiHelper;
use App\Helpers\RoleHelper;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturnItem;
use App\Models\Customers\SaleItems;
use App\Models\Setups\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CustomerReturnOrdersStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

            // Return items
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|exists:sale_items,id',
            'items.*.quantity' => 'required|numeric|min:1',
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
            'items.*.sale_item_id.required' => 'Sale item is required for all items',
            'items.*.sale_item_id.exists' => 'Selected sale item does not exist',
            'items.*.quantity.required' => 'Quantity is required for all items',
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
            /** @var \App\Models\User $user */
            $user = Auth::user();
    
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

            // Validate sale items exist and belong to valid sales
            if ($this->input('items')) {
                foreach ($this->input('items') as $index => $item) {
                    
                        $saleItem = SaleItems::with('sale')->find($item['sale_item_id']);
                        if (!$saleItem) {
                            $validator->errors()->add("items.{$index}.sale_item_id", 'Selected sale item does not exist');
                        } else {
                            // Validate sale item belongs to the selected customer
                            if ( $saleItem->sale && $saleItem->sale->customer_id !== (int) $this->input('customer_id')) {
                                $validator->errors()->add("items.{$index}.sale_item_id", 'Sale item does not belong to the selected customer');
                            }

                            // Check existing returns for this sale item (both pending and approved)
                            $existingReturns = CustomerReturnItem::where('sale_item_id', $item['sale_item_id'])
                                ->whereHas('customerReturn', function ($query) {
                                    // Include both pending (approved_by is null) and approved (approved_by is not null) returns
                                    $query->whereNull('deleted_at');
                                })
                                ->sum('quantity');

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
