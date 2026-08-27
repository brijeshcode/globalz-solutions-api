<?php

namespace App\Http\Requests\Api\Items;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ItemOffersStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function failedAuthorization(): never
    {
        throw new HttpResponseException(
            response()->json(['message' => 'Only admin users can create item offers.'], 403)
        );
    }

    public function rules(): array
    {
        return [
            'item_id'             => 'required|integer|exists:items,id',
            'free_item_id'        => 'required|integer|exists:items,id',
            'date'                => 'required|date',
            'validity_date'       => 'required|date|after_or_equal:date',
            'minimum_quantity'    => 'required|integer|min:1',
            'free_quantity'       => 'required|integer|min:1',
            'usage_limit'         => 'required|integer|min:1',
            'can_change_quantity' => 'nullable|boolean',
            'allow_multiple'      => 'nullable|boolean',
            'is_active'           => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required'          => 'Item is required',
            'item_id.exists'            => 'Selected item does not exist',
            'free_item_id.required'     => 'Free item is required',
            'free_item_id.exists'       => 'Selected free item does not exist',
            'date.required'             => 'Start date is required',
            'validity_date.required'    => 'Validity date is required',
            'validity_date.after_or_equal' => 'Validity date must be on or after the start date',
            'minimum_quantity.required' => 'Minimum quantity is required',
            'minimum_quantity.min'      => 'Minimum quantity must be at least 1',
            'free_quantity.required'    => 'Free quantity is required',
            'free_quantity.min'         => 'Free quantity must be at least 1',
            'usage_limit.required'      => 'Usage limit is required',
            'usage_limit.min'           => 'Usage limit must be at least 1',
        ];
    }
}
