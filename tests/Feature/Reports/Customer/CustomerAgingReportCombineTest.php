<?php

/**
 * Aging report under the "combine parent/child balance" feature:
 *   - total_balance sums only parents/standalone (children folded into parent)
 *   - a child row shows a zero balance
 */

use App\Helpers\FeatureHelper;
use App\Http\Controllers\Api\Reports\Customer\CustomerAgingReportController;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Landlord\Feature;
use App\Models\Landlord\TenantFeature;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->currency = Currency::factory()->create(['is_active' => true]);
    User::factory()->create();

    $feature = Feature::firstOrCreate(
        ['key' => 'combine_parent_child_balance'],
        ['name' => 'Combine Parent/Child Balance', 'description' => 'Test seeder', 'is_active' => true]
    );
    TenantFeature::updateOrCreate(
        ['tenant_id' => Tenant::current()->id, 'feature_id' => $feature->id],
        ['is_enabled' => true]
    );
    TenantFeature::clearCache(Tenant::current()->id);
    FeatureHelper::flush();
});

afterEach(function () {
    // Landlord rows persist across tests — reset so other suites see combine OFF.
    $feature = Feature::where('key', 'combine_parent_child_balance')->first();
    if ($feature) {
        TenantFeature::where('feature_id', $feature->id)->update(['is_enabled' => false]);
        TenantFeature::clearCache(Tenant::current()->id);
    }
    FeatureHelper::flush();
});

function runAgingReport(array $params = []): array
{
    return (new CustomerAgingReportController())->index(new Request($params + ['per_page' => 200]))->getData(true);
}

it('excludes child balances from total_balance and shows the child row as zero', function () {
    $parent = Customer::factory()->create(['is_active' => true, 'salesperson_id' => null]);
    $child  = Customer::factory()->create(['is_active' => true, 'salesperson_id' => null, 'parent_id' => $parent->id]);

    // Give both customers activity so they appear in the report (notes count immediately).
    foreach ([$parent, $child] as $customer) {
        CustomerCreditDebitNote::factory()->debit()->create([
            'customer_id' => $customer->id,
            'currency_id' => $this->currency->id,
            'date'        => '2025-06-01 09:00:00',
        ]);
    }

    // Set known stored balances AFTER note creation (note events mutate current_balance).
    $parent->update(['current_balance' => -140]);
    $child->update(['current_balance' => 500]);

    $response = runAgingReport();

    // Total excludes the child's stored 500; only the parent's combined balance counts.
    expect((float) $response['stats']['total_balance'])->toEqual(-140.0);

    $childRow = collect($response['data'])->firstWhere('customer_id', $child->id);
    expect((float) $childRow['balance'])->toEqual(0.0);
});
