<?php

namespace App\Http\Requests\Api\Employees;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class CommissionTargetsStoreRequest extends FormRequest
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
            'date' => 'required|date',
            'name' => 'required|string|max:50|unique:commission_targets,name',
            'note' => 'nullable|string',

            // Commission target rules (nested items)
            'rules' => 'required|array|min:1',
            'rules.*.type' => 'required|in:fuel,payment,sale',
            'rules.*.percent_type' => 'required|in:fixed,dynamic',
            'rules.*.include_type' => 'required|in:Own,All,All except own',
            'rules.*.minimum_amount' => 'required|numeric|min:0|max:9999999999.9999',
            'rules.*.maximum_amount' => 'required|numeric|min:0|max:9999999999.9999|gte:rules.*.minimum_amount',
            'rules.*.percent' => 'required|numeric|min:0|max:100',
            'rules.*.comission_label' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Commission target date is required',
            'name.required' => 'Commission target name is required',
            'name.unique' => 'Commission target name already exists',
            'name.max' => 'Commission target name cannot exceed 50 characters',

            'rules.required' => 'At least one commission rule is required',
            'rules.min' => 'At least one commission rule is required',
            'rules.*.type.required' => 'Rule type is required for each rule',
            'rules.*.type.in' => 'Rule type must be one of: fule, payment, sale',
            'rules.*.minimum_amount.required' => 'Minimum amount is required for each rule',
            'rules.*.minimum_amount.min' => 'Minimum amount must be 0 or greater',
            'rules.*.maximum_amount.required' => 'Maximum amount is required for each rule',
            'rules.*.maximum_amount.min' => 'Maximum amount must be 0 or greater',
            'rules.*.maximum_amount.gte' => 'Maximum amount must be greater than or equal to minimum amount',
            'rules.*.percent.required' => 'Percent is required for each rule',
            'rules.*.percent.min' => 'Percent must be 0 or greater',
            'rules.*.percent.max' => 'Percent cannot exceed 100',
            'rules.*.rate.required' => 'Rate is required for each rule',
            'rules.*.rate.min' => 'Rate must be 0 or greater',
            'rules.*.rate.max' => 'Rate cannot exceed 9.9999',
            'rules.*.comission_label.required' => 'Commission label is required for each rule',
            'rules.*.comission_label.max' => 'Commission label cannot exceed 100 characters',
        ];
    }

    public function attributes(): array
    {
        return [
            'rules.*.minimum_amount' => 'minimum amount',
            'rules.*.maximum_amount' => 'maximum amount',
            'rules.*.percent' => 'percent',
            'rules.*.rate' => 'rate',
            'rules.*.comission_label' => 'commission label',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that rules don't have overlapping amount ranges for the same type
            // and that all rules of the same type have the same include_type
            if ($this->has('rules') && is_array($this->rules)) {
                $rulesByType = [];
                $includeTypeByType = [];

                foreach ($this->rules as $index => $rule) {
                    if (!isset($rule['type']) || !isset($rule['minimum_amount']) || !isset($rule['maximum_amount'])) {
                        continue;
                    }

                    $type = $rule['type'];
                    $includeType = $rule['include_type'] ?? null;

                    if (!isset($rulesByType[$type])) {
                        $rulesByType[$type] = [];
                        $includeTypeByType[$type] = $includeType;
                    }

                    // Validate that include_type is consistent for the same type
                    if ($includeType !== null && $includeTypeByType[$type] !== null) {
                        if ($includeTypeByType[$type] !== $includeType) {
                            $validator->errors()->add(
                                "rules.{$index}.include_type",
                                "All rules with type '{$type}' must have the same include_type. Expected '{$includeTypeByType[$type]}' but got '{$includeType}'."
                            );
                        }
                    }

                    // Check for overlaps with existing rules of the same type
                    foreach ($rulesByType[$type] as $existingRule) {
                        $min1 = (float) $rule['minimum_amount'];
                        $max1 = (float) $rule['maximum_amount'];
                        $min2 = (float) $existingRule['minimum_amount'];
                        $max2 = (float) $existingRule['maximum_amount'];

                        // Check if ranges overlap
                        if ($min1 <= $max2 && $max1 >= $min2) {
                            $validator->errors()->add(
                                "rules.{$index}.minimum_amount",
                                "Rule amount range overlaps with another rule of the same type"
                            );
                            break;
                        }
                    }

                    $rulesByType[$type][] = $rule;
                }
            }
        });
    }
}
