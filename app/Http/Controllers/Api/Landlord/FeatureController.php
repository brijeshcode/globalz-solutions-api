<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Landlord\Feature;
use App\Models\Landlord\TenantFeature;
use App\Models\Tenant;
use App\Services\Tenants\TenantFeatureSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeatureController extends Controller
{
    public function __construct(private TenantFeatureSyncService $sync) {}

    // ─── Default feature catalog ──────────────────────────────────────────────

    public const DEFAULT_FEATURES = [
        // Sales & Customers
        ['key' => 'sale_orders',               'name' => 'Sale Orders',                 'description' => 'Pending/draft sale orders that require approval before becoming a sale.'],
        ['key' => 'customer_payment_orders',   'name' => 'Customer Payment Orders',     'description' => 'Pending payment orders that require approval before becoming a customer payment.'],
        ['key' => 'customer_returns',          'name' => 'Customer Returns',            'description' => 'Allow customers to return sold goods.'],
        ['key' => 'customer_return_orders',    'name' => 'Customer Return Orders',      'description' => 'Pending return orders that require approval. Requires customer_returns.'],
        ['key' => 'customer_credit_notes',     'name' => 'Customer Credit/Debit Notes', 'description' => 'Issue credit or debit notes against customer accounts.'],

        // Purchases & Suppliers
        ['key' => 'purchase_returns',          'name' => 'Purchase Returns',            'description' => 'Return purchased goods back to suppliers.'],
        ['key' => 'supplier_credit_notes',     'name' => 'Supplier Credit/Debit Notes', 'description' => 'Record credit or debit notes received from suppliers.'],

        // Expenses
        ['key' => 'expense',                    'name' => 'Expense transaction',        'description' => 'Enable expense module.'],
        ['key' => 'expense_deferred_payment',  'name' => 'Expense Deferred Payment',    'description' => 'Create an expense now and pay it in full or partial instalments later.'],

        // Finance & Accounts
        ['key' => 'multi_currency',            'name' => 'Multi Currency',              'description' => 'Enable multiple currencies with exchange rate management.'],
        ['key' => 'income_transactions',       'name' => 'Income Transactions',         'description' => 'Record non-sale income entries against accounts.'],
        ['key' => 'account_transfers',         'name' => 'Account Transfers',           'description' => 'Transfer money between internal accounts.'],
        ['key' => 'account_adjustments',       'name' => 'Account Adjustments',         'description' => 'Manually adjust account balances.'],

        // Inventory
        ['key' => 'item_transfers',            'name' => 'Item Transfers',              'description' => 'Transfer stock between warehouses.'],
        ['key' => 'item_adjustments',          'name' => 'Item Adjustments',            'description' => 'Manually adjust item stock quantities.'],
        ['key' => 'price_lists',               'name' => 'Price Lists',                 'description' => 'Manage multiple price lists per item.'],
        ['key' => 'item_cost_history',         'name' => 'Item Cost History',           'description' => 'Track and audit item cost changes over time.'],

        // HR / Employees
        ['key' => 'employee_management',       'name' => 'Employee Management',         'description' => 'Full HR module: employee records and departments.'],
        ['key' => 'salary_management',         'name' => 'Salary Management',           'description' => 'Process monthly employee salaries.'],
        ['key' => 'employee_commissions',      'name' => 'Employee Commissions',        'description' => 'Define commission targets and calculate earned commissions.'],
        ['key' => 'advance_loans',             'name' => 'Advance Loans',               'description' => 'Track employee advance loans and deductions.'],

        // Reports
        ['key' => 'report_capital',            'name' => 'Capital Report',              'description' => 'Business capital tracker report.'],
        ['key' => 'report_profit',             'name' => 'Monthly Profit Report',       'description' => 'Monthly profit and loss report.'],
        ['key' => 'report_employee_sales',     'name' => 'Employee Sales Report',       'description' => 'Monthly employee sales report.'],
        ['key' => 'report_expense_analysis',   'name' => 'Expense Analysis Report',     'description' => 'Detailed expense breakdown and analysis.'],
        ['key' => 'report_warehouse',          'name' => 'Warehouse Report',            'description' => 'Inventory stock levels by warehouse.'],
        ['key' => 'report_sales_category',     'name' => 'Sales Category Report',       'description' => 'Sales performance broken down by category.'],
        ['key' => 'report_customer_aging',     'name' => 'Customer Aging Report',       'description' => 'Customer outstanding balance aging by last invoice and payment date.'],
        ['key' => 'report_item_sales',     'name' => 'Item Sales Report',           'description' => 'Aggregated sales report per item showing quantity sold, sale amount, profit, and profit percentage.'],
        ['key' => 'report_vat',            'name' => 'VAT Report',                  'description' => 'VAT summary report showing collected VAT on sales, VAT returned on customer returns, VAT paid via purchases and expenses, and the net VAT difference.'],

        // Exports
        ['key' => 'export_customers',      'name' => 'Export Customers',            'description' => 'Allow exporting the customer list to an Excel file.'],

        // System
        ['key' => 'activity_logs',             'name' => 'Activity Logs',               'description' => 'Full audit trail of all user actions.'],
        ['key' => 'tax_codes',                 'name' => 'Tax Codes',                   'description' => 'Manage tax codes and apply them to invoices.'],
        ['key' => 'database_mirror',           'name' => 'Database Mirror',             'description' => 'Mirror tenant database to a remote MySQL server automatically every 30 minutes or on demand.'],
    ];

    public function seedDefaultFeatures(): JsonResponse
    {
        $inserted = 0;
        $skipped  = 0;

        foreach (self::DEFAULT_FEATURES as $data) {
            if (Feature::where('key', $data['key'])->exists()) {
                $skipped++;
                continue;
            }

            Feature::create([
                'key'         => $data['key'],
                'name'        => $data['name'],
                'description' => $data['description'],
                'is_active'   => true,
            ]);

            $inserted++;
        }

        return ApiResponse::show('Default features seeded successfully.', [
            'inserted' => $inserted,
            'skipped'  => $skipped,
        ]);
    }

    public function index(): JsonResponse
    {
        $features = Feature::orderBy('name')->get();

        return ApiResponse::show('Features retrieved successfully.', $features);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'key'         => 'required|string|max:255|unique:features,key',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::failValidation($validator->errors());
        }

        $feature = Feature::create($validator->validated());

        return ApiResponse::store('Feature created successfully.', $feature);
    }

    public function update(Request $request, Feature $feature): JsonResponse
    {
        if ($request->has('key')) {
            return ApiResponse::customError('Feature key cannot be changed after creation.', 422);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::failValidation($validator->errors());
        }

        $feature->update($validator->validated());

        return ApiResponse::update('Feature updated successfully.', $feature);
    }

    public function destroy(Feature $feature): JsonResponse
    {
        $feature->delete();

        return ApiResponse::delete('Feature deleted successfully.');
    }

    public function getTenantFeatures(Tenant $tenant): JsonResponse
    {
        $features = Feature::with(['tenantFeatures' => function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }])
        ->orderBy('name')
        ->get()
        ->map(function ($feature) {
            $tenantFeature = $feature->tenantFeatures->first();

            return [
                'id'          => $feature->id,
                'name'        => $feature->name,
                'key'         => $feature->key,
                'description' => $feature->description,
                'is_active'   => $feature->is_active,
                'is_enabled'  => $tenantFeature ? $tenantFeature->is_enabled : false,
                'settings'    => $tenantFeature ? $tenantFeature->settings : null,
            ];
        });

        return ApiResponse::show('Tenant features retrieved successfully.', [
            'tenant'   => ['id' => $tenant->id, 'name' => $tenant->name],
            'features' => $features,
        ]);
    }

    public function assignFeatureToTenant(Request $request, Tenant $tenant, Feature $feature): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_enabled' => 'required|boolean',
            'settings'   => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::failValidation($validator->errors());
        }

        $tenantFeature = TenantFeature::updateOrCreate(
            ['tenant_id' => $tenant->id, 'feature_id' => $feature->id],
            ['is_enabled' => $request->is_enabled, 'settings' => $request->settings]
        );

        $this->sync->syncSingleFeature($tenant, $feature->id, $request->is_enabled);

        return ApiResponse::update('Feature assigned to tenant successfully.', $tenantFeature);
    }

    public function bulkUpdateTenantFeatures(Request $request, Tenant $tenant): JsonResponse
    {
        info('TenantFeature bulk applying here');
        $validator = Validator::make($request->all(), [
            'features'                => 'required|array',
            'features.*.feature_id'   => 'required|exists:mysql.features,id',
            'features.*.is_enabled'   => 'required|boolean',
            'features.*.settings'     => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::failValidation($validator->errors());
        }

        foreach ($request->features as $featureData) {
            TenantFeature::updateOrCreate(
                ['tenant_id' => $tenant->id, 'feature_id' => $featureData['feature_id']],
                ['is_enabled' => $featureData['is_enabled'], 'settings' => $featureData['settings'] ?? null]
            );
        }

        $this->sync->pushToTenantSettings($tenant);

        return ApiResponse::successMessage('Tenant features updated successfully.');
    }
}
