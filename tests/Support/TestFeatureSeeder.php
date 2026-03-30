<?php

namespace Tests\Support;

use App\Models\Landlord\Feature;
use App\Models\Landlord\TenantFeature;
use App\Models\Tenant;

/**
 * Seeds feature flags for the test tenant.
 * Called once per test suite in TestCase::initTenant().
 *
 * Add feature keys here as new modules are covered by tests.
 * Do NOT enable expense_deferred_payment — not yet tested.
 */
class TestFeatureSeeder
{
    /**
     * Features to enable for the test tenant.
     * All features enabled except expense_deferred_payment (not yet tested).
     */
    private const ENABLED_FEATURES = [
        // Sales & Customers
        'sale_orders',
        'customer_payment_orders',
        'customer_returns',
        'customer_return_orders',
        'customer_credit_notes',

        // Purchases & Suppliers
        'purchase_returns',
        'supplier_credit_notes',

        // Expenses
        'expense',
        // 'expense_deferred_payment' — not yet tested

        // Finance & Accounts
        'multi_currency',
        'income_transactions',
        'account_transfers',
        'account_adjustments',

        // Inventory
        'item_transfers',
        'item_adjustments',
        'price_lists',
        'item_cost_history',

        // HR / Employees
        'employee_management',
        'salary_management',
        'employee_commissions',
        'advance_loans',

        // Reports
        'report_capital',
        'report_profit',
        'report_employee_sales',
        'report_expense_analysis',
        'report_warehouse',
        'report_sales_category',
        'report_customer_aging',
        'report_item_sales',

        // System
        'activity_logs',
        'tax_codes',
    ];

    public static function seed(Tenant $tenant): void
    {
        foreach (self::ENABLED_FEATURES as $key) {
            $feature = Feature::on('mysql')->firstOrCreate(
                ['key' => $key],
                [
                    'name'        => $key,
                    'description' => "Test seeder: {$key}",
                    'is_active'   => true,
                ]
            );

            TenantFeature::on('mysql')->updateOrCreate(
                ['tenant_id' => $tenant->id, 'feature_id' => $feature->id],
                ['is_enabled' => true, 'settings' => null]
            );
        }

        // Clear the static cache so TenantFeature::isEnabled() picks up the new records
        TenantFeature::clearCache($tenant->id);
    }
}
