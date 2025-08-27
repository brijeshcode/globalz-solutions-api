<?php

namespace App\Http\Requests\Api\Setups\Employees;

use Illuminate\Foundation\Http\FormRequest;

class DepartmentsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:departments,name',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'department name is required.',
            'name.unique' => 'This department name already exists.',
            'name.max' => 'department name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}
