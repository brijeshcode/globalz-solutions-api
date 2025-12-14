<?php

namespace App\Models\Items;

use App\Models\Setups\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemMovement extends Model
{
    /**
     * The table/view associated with the model.
     *
     * @var string
     */
    protected $table = 'item_movements_view';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The primary key for the model.
     * Note: This is not a true primary key since it's a view, but we need to define it for Laravel
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'transaction_date' => 'datetime',
        'quantity' => 'integer',
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
    ];

    /**
     * Get the item associated with the movement.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Get the warehouse associated with the movement.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Get the user who created the movement.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to filter by item ID.
     */
    public function scopeByItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope a query to filter by warehouse ID.
     */
    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, ?string $fromDate, ?string $toDate)
    {
        if ($fromDate) {
            $query->where('transaction_date', '>=', $fromDate);
        }

        if ($toDate) {
             $to_date_midnight = $toDate . ' 23:59:59';
            $query->where('transaction_date', '<=', $to_date_midnight);
        }

        return $query;
    }

    /**
     * Scope a query to filter by transaction type.
     */
    public function scopeByTransactionType($query, string $transactionType)
    {
        return $query->where('transaction_type_key', $transactionType);
    }

    /**
     * Scope a query to order by date (descending by default).
     */
    public function scopeOrderByDate($query, string $direction = 'desc')
    {
        return $query->orderBy('transaction_date', $direction)
                     ->orderBy('id', $direction);
    }
}
