<?php

namespace App\Http\Requests\Api\Settings\Items;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class ItemCatalogSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'inv_show_qrcode'   => 'sometimes|boolean',
            'inv_external_link' => 'sometimes|nullable|string|max:500',
            'inv_internal_link' => 'sometimes|nullable|string|max:500',
            'inv_active_link'   => 'sometimes|nullable|string|in:internal,external',
            'inv_label'         => 'sometimes|nullable|string|max:255',
            'inx_show_qrcode'   => 'sometimes|boolean',
            'inx_external_link' => 'sometimes|nullable|string|max:500',
            'inx_internal_link' => 'sometimes|nullable|string|max:500',
            'inx_active_link'   => 'sometimes|nullable|string|in:internal,external',
            'inx_label'         => 'sometimes|nullable|string|max:255',
        ];
    }
}
