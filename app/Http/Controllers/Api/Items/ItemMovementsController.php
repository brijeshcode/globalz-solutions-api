<?php

namespace App\Http\Controllers\Api\Items;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Items\Item;
use App\Models\Items\ItemMovement;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemMovementsController extends Controller
{
    use HasPagination;

    /**
     * Get item movements/transactions log
     * Fetches transactions from: sales, purchases, purchase returns, sale returns, transfers, adjustments
     *
     * Filters: from_date, to_date, search (item code/name), item_id, warehouse_id, transaction_type
     * Columns: date, transaction_number, transaction_type, credit (in), debit (out), balance, by, action
     */
    public function index(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');
        $warehouseId = $request->get('warehouse_id');
        $search = $request->get('search');
        $transactionType = $request->get('transaction_type');

        // Find item by ID or search term if provided
        $item = null;
        if ($itemId) {
            $item = Item::find($itemId);
        } elseif ($search) {
            $item = Item::where('code', $search)
                ->orWhere('description', 'like', "%{$search}%")
                ->first();
        }

        // Build query
        $query = ItemMovement::query()
            ->with(['item:id,code,description', 'warehouse:id,name', 'createdBy:id,name']);

        // Apply filters
        if ($item) {
            $query->byItem($item->id);
        }

        if ($warehouseId) {
            $query->byWarehouse($warehouseId);
        }

        if ($transactionType) {
            $query->byTransactionType($transactionType);
        }

        $query->byDateRange(
            $request->get('from_date'),
            $request->get('to_date')
        );

        // Order by date descending (latest on top)
        $query->orderByDate('desc');

        // Check if pagination is requested
        if ($request->boolean('withPage')) {
            $paginated = $query->paginate($this->getPerPage($request));

            // Transform paginated data
            $transformedData = $paginated->through(function ($movement) {
                return $this->transformMovement($movement);
            });

            // Calculate running balance for paginated items
            $itemsWithBalance = $this->calculateRunningBalance($transformedData->items());

            // Calculate stats based on ALL matching records (not just paginated)
            $stats = $this->calculateStats($item?->id, $warehouseId, $transactionType, $request);

            // Replace items with balanced items
            $transformedData->setCollection(collect($itemsWithBalance));

            return ApiResponse::paginated(
                'Item movements retrieved successfully',
                $transformedData,
                null,
                $stats
            );
        }

        // Get all results
        $movements = $query->get();
        $transformedMovements = $movements->map(fn($m) => $this->transformMovement($m))->toArray();

        // Calculate running balance
        $movementsWithBalance = $this->calculateRunningBalance($transformedMovements);

        // Calculate stats
        $stats = [
            'total_credit' => collect($movementsWithBalance)->sum('credit'),
            'total_debit' => collect($movementsWithBalance)->sum('debit'),
            'balance' => collect($movementsWithBalance)->first()['balance'] ?? 0,
        ];

        return ApiResponse::index(
            'Item movements retrieved successfully',
            $movementsWithBalance,
            $stats
        );
    }

    /**
     * Transform a movement model to the response format
     */
    private function transformMovement(ItemMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'parent_id' => $movement->parent_id,
            'code' => $movement->transaction_code,
            'type' => $movement->transaction_type,
            'date' => $movement->transaction_date,
            'quantity' => $movement->quantity,
            'debit' => $movement->debit,
            'credit' => $movement->credit,
            'note' => $movement->note,
            'item' => [
                'id' => $movement->item->id,
                'code' => $movement->item->code,
                'name' => $movement->item->name,
            ],
            'warehouse' => [
                'id' => $movement->warehouse->id,
                'name' => $movement->warehouse->name,
            ],
            'by' => $movement->createdBy->name ?? 'N/A',
            'transaction_type' => $movement->transaction_type_key,
            'source_table' => $movement->source_table,
            'timestamp' => $movement->timestamp,
        ];
    }

    /**
     * Calculate running balance for transactions
     * Since transactions are in descending order (latest first), we reverse, calculate, then reverse back
     */
    private function calculateRunningBalance(array $transactions): array
    {
        // Reverse to oldest first
        $reversed = array_reverse($transactions);

        $balance = 0;
        $withBalance = array_map(function ($transaction) use (&$balance) {
            $balance += $transaction['credit'] - $transaction['debit'];
            $transaction['balance'] = $balance;
            return $transaction;
        }, $reversed);

        // Reverse back to newest first
        return array_reverse($withBalance);
    }

    /**
     * Calculate statistics for all matching records (not just paginated)
     */
    private function calculateStats(?int $itemId, ?int $warehouseId, ?string $transactionType, Request $request): array
    {
        $query = ItemMovement::query();

        if ($itemId) {
            $query->byItem($itemId);
        }

        if ($warehouseId) {
            $query->byWarehouse($warehouseId);
        }

        if ($transactionType) {
            $query->byTransactionType($transactionType);
        }

        $query->byDateRange(
            $request->get('from_date'),
            $request->get('to_date')
        );

        return [
            'total_credit' => $query->sum('credit'),
            'total_debit' => $query->sum('debit'),
            'balance' => $query->sum('credit') - $query->sum('debit'),
        ];
    }
}
