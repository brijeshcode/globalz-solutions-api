<?php

namespace App\Jobs;

use App\Models\Suppliers\Purchase;
use App\Services\Customers\SaleProfitRecalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecalculateSaleProfitForPurchaseJob implements ShouldQueue
{
    use Queueable;

    public $tries  = 3;
    public $backoff = [10, 30, 60];

    public function __construct(public readonly int $purchaseId) {}

    public function handle(SaleProfitRecalculationService $service): void
    {
        $purchase = Purchase::find($this->purchaseId);

        if (!$purchase) {
            Log::warning('RecalculateSaleProfitForPurchaseJob: purchase not found', [
                'purchase_id' => $this->purchaseId,
            ]);
            return;
        }

        try {
            $result = $service->recalculateForPurchase($purchase);

            Log::info('Sale profit recalculation completed for purchase', [
                'purchase_id'        => $this->purchaseId,
                'updated_sale_items' => $result['updated_sale_items'] ?? 0,
                'updated_sales'      => $result['updated_sales'] ?? 0,
                'skipped'            => $result['skipped'] ?? false,
            ]);
        } catch (\Exception $e) {
            Log::error('RecalculateSaleProfitForPurchaseJob failed', [
                'purchase_id' => $this->purchaseId,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RecalculateSaleProfitForPurchaseJob failed permanently', [
            'purchase_id' => $this->purchaseId,
            'error'       => $exception->getMessage(),
        ]);
    }
}
