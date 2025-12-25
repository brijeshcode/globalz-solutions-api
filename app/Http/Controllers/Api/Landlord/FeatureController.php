<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Feature;
use App\Models\Landlord\TenantFeature;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeatureController extends Controller
{
    /**
     * Get all features
     */
    public function index()
    {
        $features = Feature::orderBy('name')->get();

        return response()->json([
            'features' => $features,
        ]);
    }

    /**
     * Create a new feature
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:255|unique:features,key',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $feature = Feature::create($validator->validated());

        return response()->json([
            'message' => 'Feature created successfully',
            'feature' => $feature,
        ], 201);
    }

    /**
     * Update a feature
     */
    public function update(Request $request, Feature $feature)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'key' => 'sometimes|string|max:255|unique:features,key,' . $feature->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $feature->update($validator->validated());

        return response()->json([
            'message' => 'Feature updated successfully',
            'feature' => $feature,
        ]);
    }

    /**
     * Delete a feature
     */
    public function destroy(Feature $feature)
    {
        $feature->delete();

        return response()->json([
            'message' => 'Feature deleted successfully',
        ]);
    }

    /**
     * Get all features for a specific tenant
     */
    public function getTenantFeatures(Tenant $tenant)
    {
        $features = Feature::with(['tenantFeatures' => function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }])
        ->orderBy('name')
        ->get()
        ->map(function ($feature) {
            $tenantFeature = $feature->tenantFeatures->first();

            return [
                'id' => $feature->id,
                'name' => $feature->name,
                'key' => $feature->key,
                'description' => $feature->description,
                'is_active' => $feature->is_active,
                'is_enabled' => $tenantFeature ? $tenantFeature->is_enabled : false,
                'settings' => $tenantFeature ? $tenantFeature->settings : null,
            ];
        });

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'features' => $features,
        ]);
    }

    /**
     * Assign/update feature for a tenant
     */
    public function assignFeatureToTenant(Request $request, Tenant $tenant, Feature $feature)
    {
        $validator = Validator::make($request->all(), [
            'is_enabled' => 'required|boolean',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantFeature = TenantFeature::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'feature_id' => $feature->id,
            ],
            [
                'is_enabled' => $request->is_enabled,
                'settings' => $request->settings,
            ]
        );

        return response()->json([
            'message' => 'Feature assigned to tenant successfully',
            'tenant_feature' => $tenantFeature,
        ]);
    }

    /**
     * Bulk update tenant features
     */
    public function bulkUpdateTenantFeatures(Request $request, Tenant $tenant)
    {
        $validator = Validator::make($request->all(), [
            'features' => 'required|array',
            'features.*.feature_id' => 'required|exists:features,id',
            'features.*.is_enabled' => 'required|boolean',
            'features.*.settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->features as $featureData) {
            TenantFeature::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'feature_id' => $featureData['feature_id'],
                ],
                [
                    'is_enabled' => $featureData['is_enabled'],
                    'settings' => $featureData['settings'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Tenant features updated successfully',
        ]);
    }
}
