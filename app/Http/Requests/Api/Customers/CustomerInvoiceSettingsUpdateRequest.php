<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class CustomerInvoiceSettingsUpdateRequest extends FormRequest
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
            'note_1'                    => 'sometimes|nullable|string|max:500',
            'show_note_1'               => 'sometimes|boolean',
            'note_2'                    => 'sometimes|nullable|string|max:500',
            'show_note_2'               => 'sometimes|boolean',
            'show_local_currency_tax'       => 'sometimes|boolean',
            'show_local_currency_total'     => 'sometimes|boolean',
            'default_invoice_currency_id'   => 'sometimes|nullable|integer|exists:currencies,id',
        ];
    }
}
