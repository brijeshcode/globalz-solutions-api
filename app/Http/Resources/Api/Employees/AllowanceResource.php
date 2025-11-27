<?php

namespace App\Http\Resources\Api\Employees;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllowanceResource extends JsonResource
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
            'allowance_code' => $this->allowance_code,
            'employee_id' => $this->employee_id,
            'account_id' => $this->account_id,
            'currency_id' => $this->currency_id,
            'currency_rate' => $this->currency_rate,
            'amount' => $this->amount,
            'amount_usd' => $this->amount_usd,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'name' => $this->employee->name,
                    'code' => $this->employee->code,
                    'address' => $this->when($this->employee->address, $this->employee->address),
                    'phone' => $this->when($this->employee->phone, $this->employee->phone),
                    'mobile' => $this->when($this->employee->mobile, $this->employee->mobile),
                    'email' => $this->when($this->employee->email, $this->employee->email),
                    'is_active' => $this->employee->is_active,
                ];
            }),

            'currency' => $this->whenLoaded('currency', function () {
                return [
                    'id' => $this->currency->id,
                    'name' => $this->currency->name,
                    'code' => $this->currency->code,
                    'symbol' => $this->when($this->currency->symbol, $this->currency->symbol),
                    'calculation_type' => $this->currency->calculation_type,
                    'symbol_position' => $this->currency->symbol_position,
                    'decimal_places' => $this->currency->decimal_places,
                    'decimal_separator' => $this->currency->decimal_separator,
                    'thousand_separator' => $this->currency->thousand_separator,
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
