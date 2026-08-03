<?php

namespace App\Jobs;

use App\Models\Suppliers\Purchase;
use App\Services\Customers\SaleProfitRecalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecalculateAllSalesProfitJob implements ShouldQueue
{
    use Queueable;

    public $tries  = 1;
    public $timeout = 3600;

    public function handle(SaleProfitRecalculationService $service): void
    {
        Log::info('RecalculateAllSalesProfitJob: started');

        try {
            $result = $service->recalculateForAllDeliveredPurchases();

            Log::info('RecalculateAllSalesProfitJob: completed', $result);
        } catch (\Exception $e) {
            Log::error('RecalculateAllSalesProfitJob failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RecalculateAllSalesProfitJob failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}
