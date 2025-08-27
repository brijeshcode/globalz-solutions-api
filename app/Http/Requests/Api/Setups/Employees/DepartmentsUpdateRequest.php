<?php

namespace App\Http\Requests\Api\Setups\Employees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentsUpdateRequest extends FormRequest
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
                Rule::unique('departments', 'name')->ignore($this->department)
            ],
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Department name is required.',
            'name.unique' => 'This department zone name already exists.',
            'name.max' => 'Department name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}