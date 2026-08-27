<?php

namespace App\Http\Requests\Api\Items;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ItemOffersUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function failedAuthorization(): never
    {
        throw new HttpResponseException(
            response()->json(['message' => 'Only admin users can update item offers.'], 403)
        );
    }

    public function rules(): array
    {
        return [
            'item_id'             => 'sometimes|required|integer|exists:items,id',
            'free_item_id'        => 'sometimes|required|integer|exists:items,id',
            'date'                => 'sometimes|required|date',
            'validity_date'       => 'sometimes|required|date|after_or_equal:date',
            'minimum_quantity'    => 'sometimes|required|integer|min:1',
            'free_quantity'       => 'sometimes|required|integer|min:1',
            'usage_limit'         => 'sometimes|required|integer|min:1',
            'can_change_quantity' => 'nullable|boolean',
            'allow_multiple'      => 'nullable|boolean',
            'is_active'           => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.exists'               => 'Selected item does not exist',
            'free_item_id.exists'          => 'Selected free item does not exist',
            'validity_date.after_or_equal' => 'Validity date must be on or after the start date',
            'minimum_quantity.min'         => 'Minimum quantity must be at least 1',
            'free_quantity.min'            => 'Free quantity must be at least 1',
            'usage_limit.min'              => 'Usage limit must be at least 1',
        ];
    }
}
