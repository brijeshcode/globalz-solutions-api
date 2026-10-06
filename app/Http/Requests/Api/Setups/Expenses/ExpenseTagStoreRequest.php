<?php

namespace App\Http\Requests\Api\Setups\Expenses;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseTagStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canDeveloper();
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255', Rule::unique('expense_tags', 'name')->whereNull('deleted_at')],
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->input('is_active', true)]);
    }
}
