<?php

namespace App\Http\Resources\Api\Reports\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAgingReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id'       => $this->id,
            'customer_code'     => $this->code,
            'customer_name'     => $this->name,
            'balance'           => (float) $this->current_balance,
            'last_invoice_date' => $this->last_invoice_date,
            'invoice_age'       => $this->invoice_age !== null ? (int) $this->invoice_age : null,
            'last_payment_date' => $this->last_payment_date,
            'payment_age'       => $this->payment_age !== null ? (int) $this->payment_age : null,
            'salesperson'       => $this->whenLoaded('salesperson', function () {
                return $this->salesperson ? [
                    'id'   => $this->salesperson->id,
                    'code' => $this->salesperson->code,
                    'name' => $this->salesperson->name,
                ] : null;
            }),
        ];
    }
}
