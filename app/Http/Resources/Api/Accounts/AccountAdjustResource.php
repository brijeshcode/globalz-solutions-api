<?php

namespace App\Http\Resources\Api\Accounts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountAdjustResource extends JsonResource
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
            'date' => $this->date?->format('Y-m-d H:i:s'),
            'code' => $this->code,
            'prefix' => $this->prefix,
            'transfer_code' => $this->transfer_code,
            'type' => $this->type,
            'account_id' => $this->account_id,
            'account' => $this->when($this->relationLoaded('account'), [
                'id' => $this->account?->id,
                'name' => $this->account?->name,
                'code' => $this->account?->code,
                'current_balance' => $this->account?->current_balance,
            ]),
            'amount' => $this->amount,
            'note' => $this->note,
            'created_by' => $this->when($this->relationLoaded('createdBy'), [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ]),
            'updated_by' => $this->when($this->relationLoaded('updatedBy'), [
                'id' => $this->updatedBy?->id,
                'name' => $this->updatedBy?->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
