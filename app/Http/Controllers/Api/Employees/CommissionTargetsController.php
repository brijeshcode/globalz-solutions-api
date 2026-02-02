<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Employees\CommissionTargetsStoreRequest;
use App\Http\Requests\Api\Employees\CommissionTargetsUpdateRequest;
use App\Http\Resources\Api\Employees\CommissionTargetResource;
use App\Http\Responses\ApiResponse;
use App\Models\Employees\CommissionTarget;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionTargetsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = $this->commissionTargetQuery($request);

        $commissionTargets = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Commission targets retrieved successfully',
            $commissionTargets,
            CommissionTargetResource::class
        );
    }

    public function store(CommissionTargetsStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $rules = $data['rules'] ?? [];
        unset($data['rules']); // Remove rules from commission target data

        DB::beginTransaction();
        try {
            // Create commission target
            $commissionTarget = CommissionTarget::create($data);

            // Create rules
            if (!empty($rules)) {
                foreach ($rules as $rule) {
                    $commissionTarget->rules()->create($rule);
                }
            }

            DB::commit();

            $commissionTarget->load([
                'rules',
                'createdBy:id,name',
                'updatedBy:id,name'
            ]);

            return ApiResponse::store(
                'Commission target created successfully',
                new CommissionTargetResource($commissionTarget)
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::customError('Failed to create commission target: ' . $e->getMessage(), 500);
        }
    }

    public function show(CommissionTarget $commissionTarget): JsonResponse
    {
        $commissionTarget->load([
            'rules',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Commission target retrieved successfully',
            new CommissionTargetResource($commissionTarget)
        );
    }

    public function update(CommissionTargetsUpdateRequest $request, CommissionTarget $commissionTarget): JsonResponse
    {
        $data = $request->validated();
        $rules = $data['rules'] ?? [];
        unset($data['rules']); // Remove rules from commission target data
        unset($data['code']); // Remove code from data (code is system generated only, not updatable)

        DB::beginTransaction();
        try {
            // Update commission target
            $commissionTarget->update($data);

            // Delete existing rules and create new ones
            $commissionTarget->rules()->delete();

            if (!empty($rules)) {
                foreach ($rules as $rule) {
                    $commissionTarget->rules()->create($rule);
                }
            }

            DB::commit();

            $commissionTarget->load([
                'rules',
                'createdBy:id,name',
                'updatedBy:id,name'
            ]);

            return ApiResponse::update(
                'Commission target updated successfully',
                new CommissionTargetResource($commissionTarget)
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::customError('Failed to update commission target: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(CommissionTarget $commissionTarget): JsonResponse
    {
        if (!RoleHelper::isSuperAdmin()) {
            return ApiResponse::customError('Only super administrators can delete commission targets', 403);
        }

        $commissionTarget->delete();

        return ApiResponse::delete('Commission target deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CommissionTarget::onlyTrashed()
            ->with([
                'rules',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('name')) {
            $query->byName($request->name);
        }

        $commissionTargets = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed commission targets retrieved successfully',
            $commissionTargets,
            CommissionTargetResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $commissionTarget = CommissionTarget::onlyTrashed()->findOrFail($id);

        $commissionTarget->restore();

        $commissionTarget->load([
            'rules',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::update(
            'Commission target restored successfully',
            new CommissionTargetResource($commissionTarget)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $commissionTarget = CommissionTarget::onlyTrashed()->findOrFail($id);

        // Delete associated rules first
        $commissionTarget->rules()->forceDelete();

        $commissionTarget->forceDelete();

        return ApiResponse::delete('Commission target permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = $this->commissionTargetQuery($request);

        $stats = [
            'total_commission_targets' => (clone $query)->count(),
        ];

        return ApiResponse::show('Commission target statistics retrieved successfully', $stats);
    }

    private function commissionTargetQuery(Request $request)
    {
        $query = CommissionTarget::query()
            ->with([
                'rules',
                'createdBy:id,name',
                'updatedBy:id,name'
            ])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('name')) {
            $query->byName($request->name);
        }

        if ($request->has('prefix')) {
            $query->byPrefix($request->prefix);
        }

        if ($request->has('from_date')) {
            $query->fromDate($request->from_date);
        }

        if ($request->has('to_date')) {
            $query->toDate($request->to_date);
        }

        return $query;
    }
}
