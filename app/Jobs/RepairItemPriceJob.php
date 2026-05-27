<?php

namespace App\Jobs;

use App\Services\Inventory\PriceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RepairItemPriceJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [5, 10, 30];

    public function __construct(public readonly int $itemId) {}

    public function handle(): void
    {
        try {
            PriceService::repairItemPrice($this->itemId);
        } catch (\Exception $e) {
            Log::error('RepairItemPriceJob failed', [
                'item_id' => $this->itemId,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RepairItemPriceJob failed permanently', [
            'item_id' => $this->itemId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
