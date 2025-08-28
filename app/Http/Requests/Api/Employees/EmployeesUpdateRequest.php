<?php

namespace App\Http\Requests\Api\Employees;

use Illuminate\Foundation\Http\FormRequest;

class EmployeesUpdateRequest extends FormRequest
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
        $employeeId = $this->route('employee')?->id;
        
        return [
            'code' => 'required|string|max:255|unique:employees,code,' . $employeeId,
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255|unique:employees,email,' . $employeeId,
            'start_date' => 'required|date',
            'department_id' => 'required|exists:departments,id',
            'user_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Employee code is required.',
            'code.unique' => 'This employee code already exists.',
            'code.max' => 'Employee code cannot exceed 255 characters.',
            'name.required' => 'Employee name is required.',
            'name.max' => 'Employee name cannot exceed 255 characters.',
            'address.max' => 'Address cannot exceed 500 characters.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'mobile.max' => 'Mobile number cannot exceed 20 characters.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email cannot exceed 255 characters.',
            'email.unique' => 'This email address is already in use.',
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Please provide a valid date.',
            'department_id.required' => 'Department is required.',
            'department_id.exists' => 'Selected department does not exist.',
            'user_id.exists' => 'Selected user does not exist.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
