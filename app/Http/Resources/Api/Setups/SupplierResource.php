<?php

namespace App\Http\Resources\Api\Setups;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            
            // Main Info Tab
            'code' => $this->code,
            'name' => $this->name,
            'supplier_type_id' => $this->supplier_type_id,
            'supplier_type' => [
                'id' => $this->supplierType?->id,
                'name' => $this->supplierType?->name,
            ],
            'country_id' => $this->country_id,
            'country' => [
                'id' => $this->country?->id,
                'name' => $this->country?->name,
                'code' => $this->country?->code,
            ],
            'opening_balance' => $this->opening_balance,
            'balance' => $this->balance, // Calculated current balance
            
            // Contact Info Tab
            'address' => $this->address,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'url' => $this->url,
            'email' => $this->email,
            'contact_person' => $this->contact_person,
            'contact_person_email' => $this->contact_person_email,
            'contact_person_mobile' => $this->contact_person_mobile,
            
            // Purchase Info Tab
            'payment_term_id' => $this->payment_term_id,
            'payment_term' => [
                'id' => $this->paymentTerm?->id,
                'name' => $this->paymentTerm?->name,
                'days' => $this->paymentTerm?->days,
                'type' => $this->paymentTerm?->type,
            ],
            'ship_from' => $this->ship_from,
            'bank_info' => $this->bank_info,
            'discount_percentage' => $this->discount_percentage,
            'currency_id' => $this->currency_id,
            'currency' => [
                'id' => $this->currency?->id,
                'name' => $this->currency?->name,
                'code' => $this->currency?->code,
                'symbol' => $this->currency?->symbol,
            ],
            
            // Other Tab
            'notes' => $this->notes,
            'attachments' => $this->attachments,
            
            // System Fields
            'is_active' => $this->is_active,
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