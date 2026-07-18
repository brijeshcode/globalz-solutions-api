<?php

namespace App\Http\Requests\Api\Settings;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class ModuleLockSettingsUpdateRequest extends FormRequest
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
        $rule = 'sometimes|integer|min:0|max:3650';

        return [
            'sale'                    => $rule,
            'sale_order'              => $rule,
            'purchase'                => $rule,
            'customer_payment'        => $rule,
            'customer_payment_order'  => $rule,
            'customer_return'         => $rule,
            'customer_return_order'   => $rule,
            'customer_credit_note'    => $rule,
            'supplier_credit_note'    => $rule,
            'supplier_payment'        => $rule,
            'expense'                 => $rule,
            'expense_payment'         => $rule,
        ];
    }
}
