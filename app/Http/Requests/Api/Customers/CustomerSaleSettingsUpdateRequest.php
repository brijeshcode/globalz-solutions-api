<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class CustomerSaleSettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'block_new_sale'               => 'required|boolean',
            'block_new_sale_order'         => 'required|boolean',
            'block_return_sale_received'   => 'required|boolean',
        ];
    }
}
