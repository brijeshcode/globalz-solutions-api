<?php

namespace App\Http\Requests\Api\Items;

use Illuminate\Foundation\Http\FormRequest;

class ItemsImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:text/csv,text/plain,application/csv,text/comma-separated-values,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'mimes:csv,xlsx,xls', // Extension validation
                'max:10240', // Max 10MB
            ],
            'skip_duplicates' => 'sometimes|boolean',
            'update_existing' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a file to import.',
            'file.file' => 'The uploaded item must be a file.',
            'file.mimes' => 'The file must be a CSV or Excel file (csv, xlsx, xls).',
            'file.max' => 'The file size cannot exceed 10MB.',
        ];
    }
}
