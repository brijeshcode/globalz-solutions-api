<?php

namespace App\Http\Requests\Api\Setups\Expenses;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseTagSyncCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'category_ids'   => 'present|array',
            'category_ids.*' => 'integer|exists:expense_categories,id',
        ];
    }
}
