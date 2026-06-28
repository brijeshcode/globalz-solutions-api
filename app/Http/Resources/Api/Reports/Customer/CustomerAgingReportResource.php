<?php

namespace App\Http\Resources\Api\Reports\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAgingReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id'         => $this->id,
            'customer_code'       => $this->code,
            'customer_name'       => $this->name,
            'balance'             => (float) $this->current_balance,
            'last_invoice_date'   => $this->last_invoice_date,
            'invoice_age'         => $this->invoice_age !== null ? (int) $this->invoice_age : null,
            'last_payment_date'   => $this->last_payment_date,
            'last_payment_amount' => $this->last_payment_amount !== null ? (float) $this->last_payment_amount : null,
            'payment_age'         => $this->payment_age !== null ? (int) $this->payment_age : null,
            'salesperson'         => $this->whenLoaded('salesperson', function () {
                return $this->salesperson ? [
                    'id'   => $this->salesperson->id,
                    'code' => $this->salesperson->code,
                    'name' => $this->salesperson->name,
                ] : null;
            }),
            'invoice_history'     => $this->whenLoaded('lastInvoices', function () {
                return $this->lastInvoices->map(fn ($s) => [
                    'id'         => $s->id,
                    'code'       => $s->prefix . $s->code,
                    'date'       => $s->date,
                    'total'      => (float) $s->total,
                    'total_usd'  => (float) $s->total_usd,
                ])->values();
            }),
            'payment_history'     => $this->whenLoaded('lastPayments', function () {
                return $this->lastPayments->map(fn ($p) => [
                    'id'         => $p->id,
                    'code'       => $p->prefix . $p->code,
                    'date'       => $p->date,
                    'amount'     => (float) $p->amount,
                    'amount_usd' => (float) $p->amount_usd,
                ])->values();
            }),
        ];
    }
}
