<?php

namespace App\Http\Requests\Api\Customers;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class CustomerReturnSettingsUpdateRequest extends FormRequest
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
            'stamp'        => 'sometimes|nullable|file|image|max:2048',
            'show_stamp'   => 'sometimes|boolean',
            'stamp_width'  => 'sometimes|nullable|string|max:10',
            'stamp_height' => 'sometimes|nullable|string|max:10',
        ];
    }

    /**
     * Normalize the "true"/"false" strings that multipart/form-data sends
     * into real booleans before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('show_stamp')) {
            $this->merge([
                'show_stamp' => filter_var($this->show_stamp, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
