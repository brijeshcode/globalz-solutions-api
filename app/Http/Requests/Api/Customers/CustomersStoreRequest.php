<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\RoleHelper;
use App\Models\Customers\Customer;
use Illuminate\Foundation\Http\FormRequest;

class CustomersStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Main Info Tab
            'parent_id' => 'nullable|exists:customers,id',
            'name' => 'required|string|max:255',
            'customer_type_id' => 'nullable|exists:customer_types,id',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'price_list_id_INV' => 'nullable|exists:price_lists,id',
            'price_list_id_INX' => 'nullable|exists:price_lists,id',
            'customer_province_id' => 'nullable|exists:customer_provinces,id',
            'customer_zone_id' => 'nullable|exists:customer_zones,id',
            
            // Balance Information
            // 'opening_balance' => 'nullable|numeric|min:-999999999.9999|max:999999999.9999',
            'total_old_sales' => 'nullable|numeric|min:-999999999.9999|max:999999999.9999',
            'current_balance' => 'nullable|numeric|min:-999999999.9999|max:999999999.9999',

            // Additional Info Tab
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'url' => 'nullable|url|max:255',
            'email' => 'nullable|email|max:255',
            'contact_name' => 'nullable|string|max:255',
            'gps_coordinates' => [
                'nullable',
                'string',
                'regex:/^-?\d{1,2}\.\d{1,7},-?\d{1,3}\.\d{1,7}$/'
            ],
            'mof_tax_number' => 'nullable|string|max:50',

            // Sales Info Tab
            'salesperson_id' => 'nullable|exists:employees,id',
            'customer_payment_term_id' => 'nullable|exists:customer_payment_terms,id',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'credit_limit' => 'nullable|numeric|min:0|max:999999999.9999',

            // Other Tab
            'notes' => 'nullable|string',

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
            'name.required' => 'The customer name is required.',
            'name.max' => 'The customer name cannot exceed 255 characters.',
            'parent_id.exists' => 'The selected parent customer does not exist.',
            'customer_type_id.exists' => 'The selected customer type does not exist.',
            'customer_group_id.exists' => 'The selected customer group does not exist.',
            'customer_province_id.exists' => 'The selected customer province does not exist.',
            'customer_zone_id.exists' => 'The selected customer zone does not exist.',
            'salesperson_id.exists' => 'The selected salesperson does not exist.',
            'customer_payment_term_id.exists' => 'The selected payment term does not exist.',
            'email.email' => 'The email must be a valid email address.',
            'url.url' => 'The URL must be a valid URL.',
            'gps_coordinates.regex' => 'The GPS coordinates must be in format: latitude,longitude (e.g., 33.9024493,35.5750987).',
            'telephone.max' => 'The telephone number cannot exceed 20 characters.',
            'mobile.max' => 'The mobile number cannot exceed 20 characters.',
            'mof_tax_number.max' => 'The MOF tax number cannot exceed 50 characters.',
            'discount_percentage.min' => 'The discount percentage must be at least 0.',
            'discount_percentage.max' => 'The discount percentage cannot exceed 100.',
            'credit_limit.min' => 'The credit limit must be at least 0.',
            // 'opening_balance.min' => 'The opening balance cannot be less than -999,999,999.9999.',
            // 'opening_balance.max' => 'The opening balance cannot exceed 999,999,999.9999.',
            'current_balance.min' => 'The current balance cannot be less than -999,999,999.9999.',
            'current_balance.max' => 'The current balance cannot exceed 999,999,999.9999.',
            'credit_limit.max' => 'The credit limit cannot exceed 999,999,999.9999.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'parent_id' => 'parent customer',
            'customer_type_id' => 'customer type',
            'customer_group_id' => 'customer group',
            'customer_province_id' => 'customer province',
            'customer_zone_id' => 'customer zone',
            'salesperson_id' => 'salesperson',
            'customer_payment_term_id' => 'payment term',
            // 'opening_balance' => 'opening balance',
            'current_balance' => 'current balance',
            'discount_percentage' => 'discount percentage',
            'credit_limit' => 'credit limit',
            'gps_coordinates' => 'GPS coordinates',
            'mof_tax_number' => 'MOF tax number',
            'contact_name' => 'contact name',
            'is_active' => 'active status',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that parent customer cannot be the same as current (for updates)
            if ($this->input('parent_id')) {
                // Additional validation can be added here for parent-child relationships
                $this->validateParentCustomer($validator);
            }

            // Validate salesperson is from Sales department
            if ($this->input('salesperson_id')) {
                $this->validateSalesperson($validator);
            }

            // Validate credit limit vs opening balance relationship
            $creditLimit = $this->input('credit_limit');
            // $openingBalance = $this->input('opening_balance');

            // if ($creditLimit && $openingBalance && $openingBalance > $creditLimit) {
                // This is just a warning - allow but could log it
                // For now, we'll allow customers to start over their credit limit
            // }
        });
    }

    /**
     * Validate parent customer relationships
     */
    private function validateParentCustomer($validator): void
    {
        $parentId = $this->input('parent_id');
        
        // Check if parent exists and is active
        $parent = Customer::find($parentId);
        if ($parent && !$parent->is_active) {
            $validator->errors()->add('parent_id', 'The selected parent customer is inactive.');
        }

        // Prevent circular references (parent cannot have its own parent as child)
        if ($parent && $parent->parent_id) {
            $validator->errors()->add('parent_id', 'Cannot select a customer that already has a parent customer.');
        }
    }

    /**
     * Validate salesperson is from Sales department
     */
    private function validateSalesperson($validator): void
    {
        $salespersonId = $this->input('salesperson_id');
        
        $employee = \App\Models\Employees\Employee::with('department')
            ->find($salespersonId);
            
        if ($employee && $employee->department && $employee->department->name !== 'Sales') {
            $validator->errors()->add(
                'salesperson_id', 
                'The selected employee must be from the Sales department. Selected employee is from: ' . $employee->department->name
            );
        }

        if ($employee && !$employee->is_active) {
            $validator->errors()->add('salesperson_id', 'The selected salesperson is inactive.');
        }
    }
}
