<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ActivityLogDetailResource;
use App\Http\Resources\ActivityLogBatchResource;
use App\Models\ActivityLog\ActivityLog;
use App\Models\ActivityLog\ActivityLogDetail;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Get all activity logs with filters
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with(['lastChangedBy'])
            ->recent();

        // Filter by model type
        if ($request->filled('model_type')) {
            $query->where('model', $request->model_type);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('last_changed_by', $request->user_id);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('timestamp', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('timestamp', '<=', $request->to_date);
        }

        // Filter by event type
        if ($request->filled('event')) {
            $query->where('last_event_type', $request->event);
        }

        // Filter by seen status
        if ($request->filled('seen')) {
            $seen = filter_var($request->seen, FILTER_VALIDATE_BOOLEAN);
            $query->where('seen_all', $seen);
        }

        // Search in model_display
        if ($request->filled('search')) {
            $query->where('model_display', 'like', "%{$request->search}%");
        }

        $activities = $query->paginate(20);

        return ApiResponse::paginated(
            'Activity logs retrieved successfully',
            $activities,
            ActivityLogResource::class
        );
    }

    /**
     * Get activity log by ID with all details
     */
    public function show($id)
    {
        $activityLog = ActivityLog::with(['lastChangedBy', 'details.changedBy'])
            ->findOrFail($id);

        return ApiResponse::show(
            'Activity log retrieved successfully',
            new ActivityLogResource($activityLog)
        );
    }

    /**
     * Get activity logs for a specific model
     */
    public function getModelActivity(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $activityLog = ActivityLog::where('model', $request->model_type)
            ->where('model_id', $request->model_id)
            ->with(['lastChangedBy', 'details.changedBy'])
            ->first();

        if (!$activityLog) {
            return ApiResponse::show(
                'No activity log found for this model',
                null
            );
        }

        return ApiResponse::show(
            'Activity log retrieved successfully',
            new ActivityLogResource($activityLog)
        );
    }

    /**
     * Get activity details grouped by batch for a specific model
     */
    public function getModelActivityByBatch(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $activityLog = ActivityLog::where('model', $request->model_type)
            ->where('model_id', $request->model_id)
            ->first();

        if (!$activityLog) {
            return ApiResponse::show(
                'No activity log found for this model',
                []
            );
        }

        // Group details by batch with relations loaded
        $detailsByBatch = $activityLog->detailsByBatch();

        // Format the response using the new batch resource
        $formatted = [];
        foreach ($detailsByBatch as $batchNo => $details) {
            $formatted[] = new ActivityLogBatchResource($details);
        }

        return ApiResponse::show(
            'Activity log batches retrieved successfully',
            $formatted
        );
    }

    /**
     * Get activity logs for a specific sale
     */
    public function getSaleActivity($saleId)
    {
        $activityLog = ActivityLog::where('model', 'App\\Models\\Customers\\Sale')
            ->where('model_id', $saleId)
            ->with(['lastChangedBy', 'details.changedBy'])
            ->first();

        if (!$activityLog) {
            return ApiResponse::show(
                'No activity log found for this sale',
                null
            );
        }

        return ApiResponse::show(
            'Sale activity log retrieved successfully',
            new ActivityLogResource($activityLog)
        );
    }

    /**
     * Mark an activity log as seen
     */
    public function markAsSeen($id)
    {
        $activityLog = ActivityLog::findOrFail($id);
        $activityLog->markAsSeen();

        return ApiResponse::successMessage('Activity log marked as seen');
    }

    /**
     * Mark an activity log as unseen
     */
    public function markAsUnseen($id)
    {
        $activityLog = ActivityLog::findOrFail($id);
        $activityLog->markAsUnseen();

        return ApiResponse::successMessage('Activity log marked as unseen');
    }
}
