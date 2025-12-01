<?php

namespace App\Http\Requests\Api\Employees;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalariesUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $salaryId = $this->route('salary'); // Assuming route parameter is named 'salary'

        return [
            'date' => 'required|date',
            'employee_id' => [
                'required',
                'exists:employees,id',
                Rule::unique('salaries')->where(function ($query) {
                    return $query->where('employee_id', $this->employee_id)
                                 ->where('month', $this->month)
                                 ->where('year', $this->year);
                })->ignore($salaryId),
            ],
            'account_id' => 'required|exists:accounts,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'sub_total' => 'required|numeric|min:0',
            'advance_payment' => 'required|numeric|min:0',
            'others' => 'required|numeric',
            'final_total' => 'required|numeric|min:0',
            'others_note' => 'nullable|string',
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Salary date is required',
            'employee_id.required' => 'Employee is required',
            'employee_id.exists' => 'Selected employee does not exist',
            'employee_id.unique' => 'Salary already exists for this employee in the selected month and year',
            'account_id.required' => 'Account is required',
            'account_id.exists' => 'Selected account does not exist',
            'month.required' => 'Month is required',
            'month.min' => 'Month must be between 1 and 12',
            'month.max' => 'Month must be between 1 and 12',
            'year.required' => 'Year is required',
            'year.min' => 'Year must be between 2000 and 2100',
            'year.max' => 'Year must be between 2000 and 2100',
            'sub_total.required' => 'Sub total is required',
            'sub_total.min' => 'Sub total must be 0 or greater',
            'advance_payment.required' => 'Advance payment is required',
            'advance_payment.min' => 'Advance payment must be 0 or greater',
            'others.required' => 'Others amount is required',
            'final_total.required' => 'Final total is required',
            'final_total.min' => 'Final total must be 0 or greater',
        ];
    }

    public function attributes(): array
    {
        return [
            'employee_id' => 'employee',
            'account_id' => 'account',
            'sub_total' => 'sub total',
            'advance_payment' => 'advance payment',
            'final_total' => 'final total',
            'others_note' => 'others note',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate employee is active
            if ($this->input('employee_id')) {
                $employee = \App\Models\Employees\Employee::find($this->input('employee_id'));
                if ($employee && !$employee->is_active) {
                    $validator->errors()->add('employee_id', 'Selected employee is inactive');
                }
            }

            // Validate final_total calculation
            $subTotal = $this->input('sub_total', 0);
            $advancePayment = $this->input('advance_payment', 0);
            $others = $this->input('others', 0);
            $finalTotal = $this->input('final_total', 0);

            if ($subTotal !== null && $advancePayment !== null && $others !== null && $finalTotal !== null) {
                $expectedFinalTotal = $subTotal - $advancePayment + $others;
                $tolerance = 0.01; // Allow small floating point differences

                if (abs($expectedFinalTotal - $finalTotal) > $tolerance) {
                    $validator->errors()->add('final_total',
                        'Final total must equal: Sub Total - Advance Payment + Others (' . number_format($expectedFinalTotal, 2) . ')');
                }
            }
        });
    }
}
