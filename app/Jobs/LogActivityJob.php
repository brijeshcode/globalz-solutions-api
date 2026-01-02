<?php

namespace App\Jobs;

use App\Services\ActivityLog\ActivityLogHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job to process activity logging asynchronously
 *
 * This job handles the creation and updating of activity logs in the background.
 * It manages batch numbers and ensures parent-child relationships are properly tracked.
 */
class LogActivityJob implements ShouldQueue
{
    use Queueable;

    /**
     * Activity data to be logged
     */
    protected array $activityData;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [5, 10, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(array $activityData)
    {
        $this->activityData = $activityData;
    }

    /**
     * Execute the job.
     */
    public function handle(ActivityLogHandler $handler): void
    {
        try {
            $handler->handle($this->activityData);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Activity logging job failed', [
                'error' => $e->getMessage(),
                'data' => $this->activityData,
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Activity logging job failed permanently', [
            'error' => $exception->getMessage(),
            'data' => $this->activityData,
        ]);
    }
}
