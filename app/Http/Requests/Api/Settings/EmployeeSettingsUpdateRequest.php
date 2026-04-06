<?php

namespace App\Http\Requests\Api\Settings;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'disable_payment_date_change' => 'sometimes|boolean',
            'disable_payment_order_date_change' => 'sometimes|boolean',
        ];
    }
}
