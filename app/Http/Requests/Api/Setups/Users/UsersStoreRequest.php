<?php

namespace App\Http\Requests\Api\Setups\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsersStoreRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(User::getRoles())],
            'is_active' => 'boolean',
            'employee_id' => [
                'nullable',
                'exists:employees,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $employee = \App\Models\Employees\Employee::find($value);
                        if ($employee && $employee->user_id !== null) {
                            $fail('This employee is already assigned to another user.');
                        }
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'User name is required.',
            'name.max' => 'User name cannot exceed 255 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email cannot exceed 255 characters.',
            'email.unique' => 'This email address is already in use.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role.required' => 'Role is required.',
            'role.in' => 'Selected role is invalid.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'employee_id.unique' => 'This employee is already assigned to another user.',
        ];
    }
}