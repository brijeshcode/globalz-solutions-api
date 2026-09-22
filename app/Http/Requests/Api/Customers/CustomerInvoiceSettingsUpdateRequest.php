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
            'inx_show_google_map_qrcode'    => 'sometimes|boolean',
            'inv_show_google_map_qrcode'    => 'sometimes|boolean',
            'template'                      => 'sometimes|string|in:template-1,template-2,template-3,template-4',
            'language'                      => 'sometimes|string|in:en,fr,ar',
            'unit_price_decimals'           => 'sometimes|integer|min:0|max:6',
            'total_decimals'                => 'sometimes|integer|min:0|max:6',
            'logo'                          => 'sometimes|nullable|file|image|max:2048',
            'stamp'                         => 'sometimes|nullable|file|image|max:2048',
            'show_logo'                     => 'sometimes|boolean',
            'show_stamp'                    => 'sometimes|boolean',
            'logo_width'                    => 'sometimes|nullable|string|max:10',
            'logo_height'                   => 'sometimes|nullable|string|max:10',
            'stamp_width'                   => 'sometimes|nullable|string|max:10',
            'stamp_height'                  => 'sometimes|nullable|string|max:10',
        ];
    }
}
