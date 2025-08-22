<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DocumentUpdateRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            // File upload (optional for updates - only if replacing the file)
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,bmp,svg,txt,csv,zip,rar|max:10240', // 10MB max
            
            // Document metadata (all optional for updates)
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'document_type' => 'nullable|string|max:100|in:contract,invoice,certificate,photo,manual,specification,report,presentation,legal,warranty,receipt,statement,agreement,proposal,quote,order,delivery,tax,insurance,license,permit,registration,identification,passport,visa,medical,educational,financial,technical,marketing,administrative,personal,other',
            
            // Organization
            'folder' => 'nullable|string|max:255',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            'sort_order' => 'nullable|integer|min:0|max:999999',
            
            // Access control and metadata
            'is_public' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.file' => 'The uploaded file is not valid.',
            'file.mimes' => 'The file must be one of the following types: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG, GIF, BMP, SVG, TXT, CSV, ZIP, RAR.',
            'file.max' => 'The file size must not exceed 10MB.',
            
            'title.max' => 'The title must not exceed 255 characters.',
            'description.max' => 'The description must not exceed 2000 characters.',
            'document_type.max' => 'The document type must not exceed 100 characters.',
            'document_type.in' => 'The selected document type is invalid.',
            
            'folder.max' => 'The folder name must not exceed 255 characters.',
            'tags.array' => 'Tags must be provided as an array.',
            'tags.max' => 'You can specify a maximum of 10 tags.',
            'tags.*.string' => 'Each tag must be a string.',
            'tags.*.max' => 'Each tag must not exceed 50 characters.',
            'sort_order.integer' => 'The sort order must be a valid integer.',
            'sort_order.min' => 'The sort order must be at least 0.',
            'sort_order.max' => 'The sort order must not exceed 999999.',
            
            'is_public.boolean' => 'The public status must be true or false.',
            'is_featured.boolean' => 'The featured status must be true or false.',
            'metadata.array' => 'Metadata must be provided as an array.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'document_type' => 'document type',
            'sort_order' => 'sort order',
            'is_public' => 'public status',
            'is_featured' => 'featured status',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert string boolean values to actual boolean
        if ($this->has('is_public')) {
            $this->merge([
                'is_public' => filter_var($this->is_public, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }

        if ($this->has('is_featured')) {
            $this->merge([
                'is_featured' => filter_var($this->is_featured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }

        // Convert sort_order to integer if provided
        if ($this->has('sort_order') && $this->sort_order !== null) {
            $this->merge([
                'sort_order' => (int) $this->sort_order
            ]);
        }

        // Parse tags if provided as JSON string
        if ($this->has('tags') && is_string($this->tags)) {
            $tags = json_decode($this->tags, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tags)) {
                $this->merge(['tags' => $tags]);
            }
        }

        // Parse metadata if provided as JSON string
        if ($this->has('metadata') && is_string($this->metadata)) {
            $metadata = json_decode($this->metadata, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($metadata)) {
                $this->merge(['metadata' => $metadata]);
            }
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Custom validation logic can be added here if needed
            // For example, checking if the user has permission to update this specific document
            
            // Validate that at least one field is being updated
            $updatableFields = [
                'file', 'title', 'description', 'document_type', 'folder', 
                'tags', 'sort_order', 'is_public', 'is_featured', 'metadata'
            ];
            
            $hasUpdates = false;
            foreach ($updatableFields as $field) {
                if ($this->has($field)) {
                    $hasUpdates = true;
                    break;
                }
            }
            
            if (!$hasUpdates) {
                $validator->errors()->add('general', 'At least one field must be provided for update.');
            }
        });
    }
}