<?php

namespace App\Http\Requests\Api\Setups;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CountriesUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('countries', 'name')->ignore($this->country)
            ],
            'code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('countries', 'code')->ignore($this->country)
            ],
            'iso2' => [
                'required',
                'string',
                'size:2',
                Rule::unique('countries', 'iso2')->ignore($this->country)
            ],
            'phone_code' => 'nullable|string|max:10|regex:/^\+\d+$/',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Country name is required.',
            'name.unique' => 'This country name already exists.',
            'name.max' => 'Country name cannot exceed 255 characters.',
            'code.required' => 'Country code is required.',
            'code.size' => 'Country code must be exactly 3 characters.',
            'code.unique' => 'This country code already exists.',
            'iso2.required' => 'ISO2 code is required.',
            'iso2.size' => 'ISO2 code must be exactly 2 characters.',
            'iso2.unique' => 'This ISO2 code already exists.',
            'phone_code.max' => 'Phone code cannot exceed 10 characters.',
            'phone_code.regex' => 'Phone code must start with + followed by numbers.',
        ];
    }
}