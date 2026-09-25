<?php

namespace Tests\Feature\Customers\Customers\Concerns;

use App\Helpers\FeatureHelper;
use App\Models\Employees\Employee;
use App\Models\Items\PriceList;
use App\Models\Landlord\Feature;
use App\Models\Landlord\TenantFeature;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Setups\Customers\CustomerGroup;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Customers\CustomerProvince;
use App\Models\Setups\Customers\CustomerType;
use App\Models\Setups\Customers\CustomerZone;
use App\Models\Setups\Employees\Department;
use App\Models\User;

trait HasCustomerSetup
{
    protected User $admin;
    protected CustomerType $customerType;
    protected CustomerGroup $customerGroup;
    protected CustomerProvince $customerProvince;
    protected CustomerZone $customerZone;
    protected CustomerPaymentTerm $customerPaymentTerm;
    protected PriceList $priceListINV;
    protected PriceList $priceListINX;
    protected Department $salesDepartment;
    protected Employee $salesperson;

    public function setUpCustomers(): void
    {
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');

        Setting::create([
            'group_name'  => 'customers',
            'key_name'    => 'code_counter',
            'value'       => '41101364',
            'data_type'   => 'number',
            'description' => 'Customer code counter starting from 41101364',
        ]);

        $this->customerType        = CustomerType::factory()->create();
        $this->customerGroup       = CustomerGroup::factory()->create();
        $this->customerProvince    = CustomerProvince::factory()->create();
        $this->customerZone        = CustomerZone::factory()->create();
        $this->customerPaymentTerm = CustomerPaymentTerm::factory()->create();
        $this->priceListINV        = PriceList::factory()->create();
        $this->priceListINX        = PriceList::factory()->create();

        $this->salesDepartment = Department::factory()->create(['name' => 'Sales']);
        $this->salesperson     = Employee::factory()->create([
            'department_id' => $this->salesDepartment->id,
            'is_active'     => true,
        ]);

        // Landlord feature rows persist across tests; ensure each starts with combine OFF.
        $this->setCombineBalance(false);
    }

    /**
     * Enable the developer-managed "combine parent/child balance" landlord feature
     * for the current test tenant.
     */
    protected function enableCombineBalance(): void
    {
        $this->setCombineBalance(true);
    }

    /**
     * Landlord feature rows persist across tests (not covered by RefreshDatabase),
     * so reset to a known state explicitly.
     */
    protected function setCombineBalance(bool $enabled): void
    {
        $feature = Feature::firstOrCreate(
            ['key' => 'combine_parent_child_balance'],
            ['name' => 'Combine Parent/Child Balance', 'description' => 'Test seeder', 'is_active' => true]
        );

        $tenantId = Tenant::current()->id;

        TenantFeature::updateOrCreate(
            ['tenant_id' => $tenantId, 'feature_id' => $feature->id],
            ['is_enabled' => $enabled]
        );

        TenantFeature::clearCache($tenantId);
        FeatureHelper::flush();
    }

    protected function customerPayload(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'Test Customer',
            'customer_type_id'      => $this->customerType->id,
            'customer_group_id'     => $this->customerGroup->id,
            'customer_province_id'  => $this->customerProvince->id,
            'customer_zone_id'      => $this->customerZone->id,
            'price_list_id_INV'     => $this->priceListINV->id,
            'price_list_id_INX'     => $this->priceListINX->id,
            'city'                  => 'Test City',
            'mobile'                => '0501234567',
            'salesperson_id'        => $this->salesperson->id,
            'current_balance'       => 0,
        ], $overrides);
    }
}
