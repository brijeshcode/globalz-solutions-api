<?php

namespace App\Http\Requests\Api\Setups\Expenses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoriesUpdateRequest extends FormRequest
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
        $expenseCategory = $this->route('expenseCategory');

        return [
            'parent_id' => [
                'nullable',
                'exists:expense_categories,id',
                function ($attribute, $value, $fail) use ($expenseCategory) {
                    if ($value == $expenseCategory->id) {
                        $fail('A category cannot be its own parent.');
                    }

                    if ($value && $this->wouldCreateCircularReference($expenseCategory, $value)) {
                        $fail('This would create a circular reference.');
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('expense_categories', 'name')
                    ->where('parent_id', $this->input('parent_id'))
                    ->ignore($expenseCategory)
                    ->whereNull('deleted_at')
            ],
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => 'The selected parent category does not exist.',
            'name.required' => 'Name is required.',
            'name.unique' => 'This name already exists within the same parent category.',
            'name.max' => 'Name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }

    /**
     * Check if setting this parent would create a circular reference.
     */
    private function wouldCreateCircularReference($category, $newParentId): bool
    {
        $descendants = $category->getAllDescendants()->pluck('id');
        return $descendants->contains($newParentId);
    }
}
