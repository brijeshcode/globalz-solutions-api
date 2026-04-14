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
            'inv_catalog_link'  => 'sometimes|nullable|string|max:500',
            'inv_catalog_label' => 'sometimes|nullable|string|max:255',
            'inx_show_qrcode'   => 'sometimes|boolean',
            'inx_catalog_link'  => 'sometimes|nullable|string|max:500',
            'inx_catalog_label' => 'sometimes|nullable|string|max:255',
        ];
    }
}
