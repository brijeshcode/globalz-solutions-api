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
            'catalog_link'  => 'sometimes|nullable|string|max:500',
            'catalog_label' => 'sometimes|nullable|string|max:255',
        ];
    }
}
