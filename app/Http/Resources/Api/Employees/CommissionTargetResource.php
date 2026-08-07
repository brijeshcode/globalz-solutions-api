<?php

namespace App\Http\Resources\Api\Employees;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Employees\CommissionTarget
 */
class CommissionTargetResource extends JsonResource
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
            'prefix' => $this->prefix,
            'commission_target_code' => $this->commission_target_code,
            'date' => $this->date?->format('Y-m-d'),
            'name' => $this->name,
            'note' => $this->note,

            // Nested rules
            'rules' => $this->whenLoaded('rules', function () {
                return $this->rules->map(function ($rule) {
                    return [
                        'id' => $rule->id,
                        'commission_target_id' => $rule->commission_target_id,
                        'type' => $rule->type,
                        'period' => $rule->period,
                        'include_type' => $rule->include_type,
                        'amount_type' => $rule->amount_type,
                        'auto_target_type' => $rule->auto_target_type,
                        'number_of_months' => $rule->number_of_months,
                        'push_target_percent' => $rule->push_target_percent,
                        'minimum_amount' => $rule->minimum_amount,
                        'maximum_amount' => $rule->maximum_amount,
                        'is_capped' => $rule->is_capped,
                        'reward_type' => $rule->reward_type,
                        'percent' => $rule->percent,
                        'fixed_reward' => $rule->fixed_reward,
                        'reward_calculation_type' => $rule->reward_calculation_type,
                        'comission_label' => $rule->comission_label,
                        'created_at' => $rule->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $rule->updated_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),

            // Audit fields
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

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

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
