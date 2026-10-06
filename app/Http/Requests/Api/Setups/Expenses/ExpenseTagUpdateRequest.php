<?php

namespace App\Http\Requests\Api\Setups\Expenses;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseTagUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canDeveloper();
    }

    public function rules(): array
    {
        $tagId = $this->route('expenseTag')?->id;

        return [
            'name'        => ['sometimes', 'string', 'max:255', Rule::unique('expense_tags', 'name')->ignore($tagId)->whereNull('deleted_at')],
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ];
    }
}
