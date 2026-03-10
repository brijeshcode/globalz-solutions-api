<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Landlord\Feature;
use App\Models\Landlord\FeatureBundle;
use App\Models\Tenant;
use App\Services\Tenants\TenantFeatureSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeatureBundleController extends Controller
{
    public function __construct(private TenantFeatureSyncService $sync) {}

    // ─── Default bundle catalog ───────────────────────────────────────────────

    public const DEFAULT_BUNDLES = [
        [
            'key'         => 'core',
            'name'        => 'Core',
            'description' => 'Base features available to all tenants: expense management, activity logs, and tax codes.',
            'features'    => [
                'expense',
                'activity_logs',
                'tax_codes',
            ],
        ],
        [
            'key'         => 'sales_pro',
            'name'        => 'Sales Pro',
            'description' => 'Advanced sales workflow: orders, returns, credit notes, and payment orders.',
            'features'    => [
                'sale_orders',
                'customer_payment_orders',
                'customer_returns',
                'customer_return_orders',
                'customer_credit_notes',
            ],
        ],
        [
            'key'         => 'procurement_pro',
            'name'        => 'Procurement Pro',
            'description' => 'Advanced purchasing: purchase returns and supplier credit/debit notes.',
            'features'    => [
                'purchase_returns',
                'supplier_credit_notes',
            ],
        ],
        [
            'key'         => 'finance_pro',
            'name'        => 'Finance Pro',
            'description' => 'Multi-currency, income tracking, account transfers, adjustments, and deferred expense payments.',
            'features'    => [
                'multi_currency',
                'income_transactions',
                'account_transfers',
                'account_adjustments',
                'expense_deferred_payment',
            ],
        ],
        [
            'key'         => 'inventory_pro',
            'name'        => 'Inventory Pro',
            'description' => 'Warehouse transfers, stock adjustments, price lists, and cost history.',
            'features'    => [
                'item_transfers',
                'item_adjustments',
                'price_lists',
                'item_cost_history',
            ],
        ],
        [
            'key'         => 'hr_module',
            'name'        => 'HR Module',
            'description' => 'Full HR suite: employees, salaries, commissions, and advance loans.',
            'features'    => [
                'employee_management',
                'salary_management',
                'employee_commissions',
                'advance_loans',
            ],
        ],
        [
            'key'         => 'reports',
            'name'        => 'Reports',
            'description' => 'All business intelligence reports: capital, profit, expenses, warehouse, and sales category.',
            'features'    => [
                'report_capital',
                'report_profit',
                'report_expense_analysis',
                'report_warehouse',
                'report_sales_category',
            ],
        ],
        [
            'key'         => 'enterprise',
            'name'        => 'Enterprise',
            'description' => 'All modules combined: Sales Pro, Procurement Pro, Finance Pro, Inventory Pro, HR Module, Reports, and system features.',
            'features'    => [
                'sale_orders',
                'customer_payment_orders',
                'customer_returns',
                'customer_return_orders',
                'customer_credit_notes',
                'purchase_returns',
                'supplier_credit_notes',
                'multi_currency',
                'income_transactions',
                'account_transfers',
                'account_adjustments',
                'expense',
                'expense_deferred_payment',
                'item_transfers',
                'item_adjustments',
                'price_lists',
                'item_cost_history',
                'employee_management',
                'salary_management',
                'employee_commissions',
                'advance_loans',
                'report_capital',
                'report_profit',
                'report_expense_analysis',
                'report_warehouse',
                'report_sales_category',
                'activity_logs',
                'tax_codes',
            ],
        ],
    ];

    /**
     * Insert all DEFAULT_BUNDLES into the feature_bundles table and attach their features.
     * Skips bundles whose key already exists. Safe to run multiple times.
     *
     * Usage from tinker:
     *   app(\App\Http\Controllers\Api\Landlord\FeatureBundleController::class)->seedDefaultBundles();
     */
    public function seedDefaultBundles(): JsonResponse
    {
        $featureMap = Feature::pluck('id', 'key');

        $inserted = 0;
        $skipped  = 0;

        foreach (self::DEFAULT_BUNDLES as $data) {
            if (FeatureBundle::where('key', $data['key'])->exists()) {
                $skipped++;
                continue;
            }

            $bundle = FeatureBundle::create([
                'key'         => $data['key'],
                'name'        => $data['name'],
                'description' => $data['description'],
                'is_active'   => true,
            ]);

            $featureIds = collect($data['features'])
                ->map(fn ($key) => $featureMap[$key] ?? null)
                ->filter()
                ->values()
                ->all();

            if (!empty($featureIds)) {
                $bundle->features()->sync($featureIds);
            }

            $inserted++;
        }

        return ApiResponse::show('Default bundles seeded successfully.', [
            'inserted' => $inserted,
            'skipped'  => $skipped,
        ]);
    }

    // ─── Bundle CRUD ──────────────────────────────────────────────────────────

    /**
     * GET /feature-bundles
     * List all bundles with their features.
     */
    public function index(): JsonResponse
    {
        $bundles = FeatureBundle::with('features:id,name,key,description,is_active')
            ->orderBy('name')
            ->get();

        return ApiResponse::show('Bundles retrieved successfully.', $bundles);
    }

    /**
     * POST /feature-bundles
     * Create a new bundle.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'key'         => 'required|string|max:255|unique:feature_bundles,key',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'integer|exists:mysql.features,id',
        ]);

        $bundle = FeatureBundle::create($validated);

        if (!empty($validated['feature_ids'])) {
            $bundle->features()->sync($validated['feature_ids']);
        }

        $bundle->load('features:id,name,key');

        return ApiResponse::store('Bundle created successfully.', $bundle);
    }

    /**
     * GET /feature-bundles/{bundle}
     * Show a single bundle with features.
     */
    public function show(FeatureBundle $featureBundle): JsonResponse
    {
        $featureBundle->load('features:id,name,key,description,is_active');

        return ApiResponse::show('Bundle retrieved successfully.', $featureBundle);
    }

    /**
     * PUT /feature-bundles/{bundle}
     * Update bundle details.
     */
    public function update(Request $request, FeatureBundle $featureBundle): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'key'         => 'sometimes|string|max:255|unique:feature_bundles,key,' . $featureBundle->id,
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $featureBundle->update($validated);
        $featureBundle->load('features:id,name,key');

        return ApiResponse::update('Bundle updated successfully.', $featureBundle);
    }

    /**
     * DELETE /feature-bundles/{bundle}
     * Delete a bundle (features are untouched).
     */
    public function destroy(FeatureBundle $featureBundle): JsonResponse
    {
        $featureBundle->delete();

        return ApiResponse::delete('Bundle deleted successfully.');
    }

    // ─── Bundle features ──────────────────────────────────────────────────────

    /**
     * POST /feature-bundles/{bundle}/features
     * Add features to a bundle (sync — replaces the list).
     */
    public function syncFeatures(Request $request, FeatureBundle $featureBundle): JsonResponse
    {
        $request->validate([
            'feature_ids'   => 'required|array',
            'feature_ids.*' => 'integer|exists:mysql.features,id',
        ]);

        $featureBundle->features()->sync($request->feature_ids);
        $featureBundle->load('features:id,name,key,description,is_active');

        return ApiResponse::update('Bundle features updated successfully.', $featureBundle);
    }

    // ─── Tenant application ───────────────────────────────────────────────────

    /**
     * POST /tenants/{tenant}/bundles/{featureBundle}/apply
     *
     * One-time template action: enables all active features from the bundle
     * in tenant_features and syncs them to the tenant settings DB.
     *
     * - Does NOT create any ongoing bundle→tenant link.
     * - After applying, features are managed individually via the feature endpoints.
     * - Features already enabled stay enabled.
     * - Features NOT in this bundle are never touched.
     */
    public function applyBundleToTenant(Tenant $tenant, FeatureBundle $featureBundle): JsonResponse
    {
        $this->sync->applyBundle($tenant, $featureBundle);

        return ApiResponse::update("Bundle \"{$featureBundle->name}\" applied to tenant successfully.", [
            'tenant'           => ['id' => $tenant->id, 'name' => $tenant->name],
            'enabled_features' => $tenant->getEnabledFeatures(),
        ]);
    }
}
