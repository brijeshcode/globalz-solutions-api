<?php

namespace App\Http\Requests\Api\Items;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriceListsStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = \Illuminate\Support\Facades\Auth::user();

        // Only admins can create price lists
        return $user && $user->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('price_lists', 'code')->whereNull('deleted_at')],
            'description' => 'nullable|string|max:500',
            'note' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.item_code' => 'required|string|max:255',
            'items.*.item_id' => 'nullable|exists:items,id',
            'items.*.item_description' => 'nullable|string',
            'items.*.sell_price' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Price list code is required',
            'code.unique' => 'Price list code already exists',
            'description.required' => 'Price list description is required',
            'items.required' => 'At least one item is required',
            'items.min' => 'At least one item is required',
            'items.*.item_code.required' => 'Item code is required for each item',
            'items.*.sell_price.required' => 'Sell price is required for each item',
            'items.*.sell_price.min' => 'Sell price must be greater than or equal to 0',
        ];
    }
}
