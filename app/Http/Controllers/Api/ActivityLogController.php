<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityHistoryResource;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Get all activity logs with filters
     */
    public function index(Request $request)
    {
        $query = Activity::with(['causer', 'subject'])
            ->latest();

        // Filter by model type
        if ($request->filled('model_type')) {
            $query->where('subject_type', $request->model_type);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in description
        if ($request->filled('search')) {
            $query->where('description', 'like', "%{$request->search}%");
        }

        $activities = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ActivityHistoryResource::collection($activities),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Get activity log by ID
     */
    public function show($id)
    {
        $activity = Activity::with(['causer', 'subject'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ActivityHistoryResource($activity),
        ]);
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

        $activities = Activity::where('subject_type', $request->model_type)
            ->where('subject_id', $request->model_id)
            ->with('causer')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => ActivityHistoryResource::collection($activities),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Get activity logs for a specific sale
     */
    public function getSaleActivity($saleId)
    {
        $activities = Activity::where('subject_type', 'App\\Models\\Customers\\Sale')
            ->where('subject_id', $saleId)
            ->with('causer')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => ActivityHistoryResource::collection($activities),
        ]);
    }
}
