<?php

namespace App\Models\Customers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaInvoiceStatusHistory extends Model
{
    protected $fillable = ['proforma_invoice_id', 'status', 'changed_by'];

    /**
     * @return BelongsTo<ProformaInvoice, $this>
     */
    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
