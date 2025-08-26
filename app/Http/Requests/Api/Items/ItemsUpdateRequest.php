<?php

namespace App\Http\Requests\Api\Items;

use App\Models\Items\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $itemId = $this->route('item')->id;

        return [
            // Main Information
            'code' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('items', 'code')->ignore($itemId)
            ],
            'short_name' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|required|string',
            'item_type_id' => 'sometimes|required|exists:item_types,id',

            // Classification Fields
            'item_family_id' => 'sometimes|nullable|exists:item_families,id',
            'item_group_id' => 'sometimes|nullable|exists:item_groups,id',
            'item_category_id' => 'sometimes|nullable|exists:item_categories,id',
            'item_brand_id' => 'sometimes|nullable|exists:item_brands,id',
            'item_unit_id' => 'sometimes|required|exists:item_units,id',
            'supplier_id' => 'sometimes|nullable|exists:suppliers,id',
            'tax_code_id' => 'sometimes|required|exists:tax_codes,id',
            'item_profit_margin_id' => 'sometimes|nullable|exists:item_profit_margins,id',

            // Physical Properties
            'volume' => 'sometimes|nullable|numeric|min:0|max:999999.9999',
            'weight' => 'sometimes|nullable|numeric|min:0|max:999999.9999',
            'barcode' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('items', 'barcode')->ignore($itemId)
            ],

            // Pricing Information
            'base_cost' => 'sometimes|nullable|numeric|min:0|max:999999999.999999',
            'base_sell' => 'sometimes|nullable|numeric|min:0|max:999999999.999999',
            'starting_price' => 'sometimes|nullable|numeric|min:0|max:999999999.999999',

            // Inventory Management
            'starting_quantity' => 'sometimes|nullable|numeric|min:0|max:999999999.999999',
            'low_quantity_alert' => 'sometimes|nullable|numeric|min:0|max:999999999.999999',

            // Cost Calculation Method
            'cost_calculation' => [
                'sometimes',
                Rule::in(Item::getCostCalculationMethods())
            ],

            // Additional Information
            'notes' => 'sometimes|nullable|string',

            // System Fields
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'description.required' => 'The item description is required.',
            'item_type_id.required' => 'The item type is required.',
            'item_type_id.exists' => 'The selected item type does not exist.',
            'item_unit_id.required' => 'The item unit is required.',
            'item_unit_id.exists' => 'The selected item unit does not exist.',
            'item_profit_margin_id.exists' => 'The selected item profit margin does not exist.',
            'tax_code_id.required' => 'The tax code is required.',
            'tax_code_id.exists' => 'The selected tax code does not exist.',
            'item_family_id.exists' => 'The selected item family does not exist.',
            'item_group_id.exists' => 'The selected item group does not exist.',
            'item_category_id.exists' => 'The selected item category does not exist.',
            'item_brand_id.exists' => 'The selected item brand does not exist.',
            'supplier_id.exists' => 'The selected supplier does not exist.',
            'code.unique' => 'This item code is already in use.',
            'barcode.unique' => 'This barcode is already in use.',
            'cost_calculation.in' => 'The cost calculation method must be either weighted average or last cost.',
            'volume.min' => 'The volume must be greater than or equal to 0.',
            'weight.min' => 'The weight must be greater than or equal to 0.',
            'base_cost.min' => 'The base cost must be greater than or equal to 0.',
            'base_sell.min' => 'The base sell price must be greater than or equal to 0.',
            'starting_price.min' => 'The starting price must be greater than or equal to 0.',
            'starting_quantity.min' => 'The starting quantity must be greater than or equal to 0.',
            'low_quantity_alert.min' => 'The low quantity alert must be greater than or equal to 0.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'item_type_id' => 'item type',
            'item_family_id' => 'item family',
            'item_group_id' => 'item group',
            'item_category_id' => 'item category',
            'item_profit_margins' => 'item profit margin',
            'item_brand_id' => 'item brand',
            'item_unit_id' => 'item unit',
            'supplier_id' => 'supplier',
            'tax_code_id' => 'tax code',
            'short_name' => 'short name',
            'base_cost' => 'base cost',
            'base_sell' => 'base sell price',
            'starting_price' => 'starting price',
            'starting_quantity' => 'starting quantity',
            'low_quantity_alert' => 'low quantity alert',
            'cost_calculation' => 'cost calculation method',
            'is_active' => 'active status',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Custom validation for pricing relationships
            $baseCost = $this->input('base_cost');
            $baseSell = $this->input('base_sell');

            // Get current values if not provided in update
            if ($baseCost === null) {
                $baseCost = $this->route('item')->base_cost;
            }
            if ($baseSell === null) {
                $baseSell = $this->route('item')->base_sell;
            }

            // Validate that low quantity alert makes sense
            $startingQty = $this->input('starting_quantity');
            $lowAlert = $this->input('low_quantity_alert');

            // Get current values if not provided in update
            if ($startingQty === null) {
                $startingQty = $this->route('item')->starting_quantity;
            }
            if ($lowAlert === null) {
                $lowAlert = $this->route('item')->low_quantity_alert;
            }

            if ($startingQty && $lowAlert && $lowAlert > $startingQty) {
                $validator->errors()->add(
                    'low_quantity_alert',
                    'The low quantity alert cannot be greater than the starting quantity.'
                );
            }
        });
    }
}