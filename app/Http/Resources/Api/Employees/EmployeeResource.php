<?php

namespace App\Http\Resources\Api\Employees;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'base_salary' => $this->base_salary,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'start_date' => $this->start_date,
            'department_id' => $this->department_id,
            'department' => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
            ],
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'zones' => $this->whenLoaded('zones', function () {
                return $this->zones->map(function ($zone) {
                    return [
                        'id' => $zone->id,
                        'name' => $zone->name,
                    ];
                });
            }),
            'warehouses' => $this->whenLoaded('warehouses', function () {
                return $this->warehouses->map(function ($warehouse) {
                    return [
                        'id' => $warehouse->id,
                        'name' => $warehouse->name,
                        'is_primary' => $warehouse->pivot->is_primary,
                    ];
                });
            }),
            'employee_commission_targets' => $this->whenLoaded('employeeCommissionTargets', function () {
                return $this->employeeCommissionTargets->map(function ($target) {
                    return [
                        'id' => $target->id,
                        'commission_target_id' => $target->commission_target_id,
                        'commission_target' => [
                            'id' => $target->commissionTarget?->id,
                            'name' => $target->commissionTarget?->name,
                        ],
                        'month' => $target->month,
                        'year' => $target->year,
                        'note' => $target->note,
                    ];
                });
            }),
            'created_by' => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ],
            'updated_by' => [
                'id' => $this->updatedBy?->id,
                'name' => $this->updatedBy?->name,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
