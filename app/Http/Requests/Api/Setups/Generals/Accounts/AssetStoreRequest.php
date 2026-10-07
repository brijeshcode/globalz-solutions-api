<?php

namespace App\Http\Requests\Api\Setups\Generals\Accounts;

use Illuminate\Foundation\Http\FormRequest;

class AssetStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'date' => 'nullable|date',
            'quantity' => 'nullable|integer|min:1',
            'currency_id' => 'nullable|exists:currencies,id',
            'currency_rate' => 'nullable|numeric|min:0',
            'amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Asset name is required.',
            'amount.required' => 'Asset amount is required.',
        ];
    }
}
