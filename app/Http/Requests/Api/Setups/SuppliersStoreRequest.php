<?php

namespace App\Http\Requests\Api\Setups;

use Illuminate\Foundation\Http\FormRequest;

class SuppliersStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Main Info Tab - code is auto-generated, not accepted from user
            'name' => 'required|string|max:255',
            'supplier_type_id' => 'nullable|exists:supplier_types,id',
            'country_id' => 'nullable|exists:countries,id',
            'opening_balance' => 'nullable|numeric|min:-999999999999.99|max:999999999999.99',
            
            // Contact Info Tab
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'url' => 'nullable|url|max:255',
            'email' => 'nullable|email|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_person_email' => 'nullable|email|max:255',
            'contact_person_mobile' => 'nullable|string|max:20',
            
            // Purchase Info Tab
            'payment_term_id' => 'nullable|exists:supplier_payment_terms,id',
            'ship_from' => 'nullable|string|max:255',
            'bank_info' => 'nullable|string|max:1000',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'currency_id' => 'nullable|exists:currencies,id',
            
            // Other Tab
            'notes' => 'nullable|string|max:2000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'string|max:255',
            
            // System
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Supplier name is required.',
            'name.max' => 'Supplier name cannot exceed 255 characters.',
            'supplier_type_id.exists' => 'Selected supplier type is invalid.',
            'country_id.exists' => 'Selected country is invalid.',
            'opening_balance.numeric' => 'Opening balance must be a valid number.',
            'opening_balance.min' => 'Opening balance is too low.',
            'opening_balance.max' => 'Opening balance is too high.',
            'address.max' => 'Address cannot exceed 1000 characters.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'mobile.max' => 'Mobile number cannot exceed 20 characters.',
            'url.url' => 'URL must be a valid web address.',
            'url.max' => 'URL cannot exceed 255 characters.',
            'email.email' => 'Email must be a valid email address.',
            'email.max' => 'Email cannot exceed 255 characters.',
            'contact_person.max' => 'Contact person name cannot exceed 255 characters.',
            'contact_person_email.email' => 'Contact person email must be a valid email address.',
            'contact_person_email.max' => 'Contact person email cannot exceed 255 characters.',
            'contact_person_mobile.max' => 'Contact person mobile cannot exceed 20 characters.',
            'payment_term_id.exists' => 'Selected payment term is invalid.',
            'ship_from.max' => 'Ship from location cannot exceed 255 characters.',
            'bank_info.max' => 'Bank info cannot exceed 1000 characters.',
            'discount_percentage.numeric' => 'Discount percentage must be a valid number.',
            'discount_percentage.min' => 'Discount percentage cannot be negative.',
            'discount_percentage.max' => 'Discount percentage cannot exceed 100%.',
            'currency_id.exists' => 'Selected currency is invalid.',
            'notes.max' => 'Notes cannot exceed 2000 characters.',
            'attachments.array' => 'Attachments must be an array.',
        ];
    }
}