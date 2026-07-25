<?php

namespace App\Http\Requests\Api\Employees;

use App\Helpers\RoleHelper;
use App\Models\Employees\CommissionTargetRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'rules.*.type' => ['required', 'string', Rule::in(CommissionTargetRule::TYPES)],
            'rules.*.period' => ['required', 'string', Rule::in(CommissionTargetRule::PERIODS)],
            'rules.*.include_type' => 'required|in:Own,All,All except own',
            'rules.*.amount_type' => 'nullable|in:set,auto',
            'rules.*.auto_target_type' => 'nullable|in:min,max,both',
            'rules.*.number_of_months' => 'nullable|integer|min:0',
            'rules.*.push_target_percent' => 'nullable|numeric|min:0|max:100',
            'rules.*.minimum_amount' => 'required|numeric|min:0|max:9999999999.9999',
            'rules.*.maximum_amount' => 'required|numeric|min:0|max:9999999999.9999',
            'rules.*.reward_type' => 'nullable|in:fixed,percent',
            'rules.*.percent' => 'required|numeric|min:0|max:100',
            'rules.*.fixed_reward' => 'nullable|numeric|min:0',
            'rules.*.reward_calculation_type' => 'required|in:fixed,dynamic',
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
            'rules.*.type.in' => 'Rule type must be one of: ' . implode(', ', CommissionTargetRule::TYPES),
            'rules.*.minimum_amount.required' => 'Minimum amount is required for each rule',
            'rules.*.minimum_amount.min' => 'Minimum amount must be 0 or greater',
            'rules.*.maximum_amount.required' => 'Maximum amount is required for each rule',
            'rules.*.maximum_amount.min' => 'Maximum amount must be 0 or greater',
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

}
