<?php

namespace App\Models\Suppliers;

use App\Models\Expenses\ExpenseTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'expense_transaction_id',
        'exclude_from_item_cost',
    ];

    protected $casts = [
        'exclude_from_item_cost' => 'boolean',
    ];

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<ExpenseTransaction, $this>
     */
    public function expenseTransaction(): BelongsTo
    {
        return $this->belongsTo(ExpenseTransaction::class);
    }
}
