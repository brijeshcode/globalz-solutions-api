<?php

namespace App\Http\Resources\Api\Employees;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'prefix' => $this->prefix,
            'code' => $this->code,
            'salary_code' => $this->salary_code,
            'employee_id' => $this->employee_id,
            'account_id' => $this->account_id,
            'month' => $this->month,
            'year' => $this->year,
            'sub_total' => $this->sub_total,
            'advance_payment' => $this->advance_payment,
            'others' => $this->others,
            'final_total' => $this->final_total,
            'net_salary' => $this->net_salary,
            'net_salary_usd' => $this->net_salary_usd,
            'others_note' => $this->others_note,
            'base_salary' => $this->base_salary,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'name' => $this->employee->name,
                    'code' => $this->employee->code,
                    'email' => $this->when($this->employee->email, $this->employee->email),
                    'is_active' => $this->employee->is_active,
                ];
            }),

            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'code' => $this->when($this->account->code, $this->account->code),
                ];
            }),

            'created_by_user' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),

            'updated_by_user' => $this->whenLoaded('updatedBy', function () {
                return [
                    'id' => $this->updatedBy->id,
                    'name' => $this->updatedBy->name,
                ];
            }),
        ];
    }
}
