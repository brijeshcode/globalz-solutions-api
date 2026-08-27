<?php

namespace App\Http\Controllers\Api;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AttachCacheVersion;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Models\Customers\Customer;
use App\Models\Employees\CommissionTarget;
use App\Models\Employees\Employee;
use App\Models\Items\Item;
use App\Models\Items\ItemOffer;
use App\Models\Items\PriceList;
use App\Models\Setups\Accounts\AccountType;
use App\Models\Setups\Accounts\IncomeCategory;
use App\Models\Setups\Country;
use App\Models\Setups\Customers\CustomerGroup;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Customers\CustomerProvince;
use App\Models\Setups\Customers\CustomerType;
use App\Models\Setups\Customers\CustomerZone;
use App\Models\Setups\Employees\Department;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\ItemBrand;
use App\Models\Setups\ItemCategory;
use App\Models\Setups\ItemFamily;
use App\Models\Setups\ItemGroup;
use App\Models\Setups\ItemProfitMargin;
use App\Models\Setups\ItemType;
use App\Models\Setups\ItemUnit;
use App\Models\Setups\Supplier;
use App\Models\Setups\SupplierPaymentTerm;
use App\Models\Setups\SupplierType;
use App\Models\Setups\TaxCode;
use App\Models\Setups\Warehouse;
use App\Models\Vehicle\Car;
use App\Models\Vehicle\GasStation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ListDataController extends Controller
{
    /**
     * Garbage-collection TTL for orphaned cache entries (7 days). Freshness is
     * entirely handled by the version in the key: when a model changes, its version
     * is bumped, the key changes, and a fresh query runs. Old keys become unreachable
     * and are evicted by this TTL. Matches the browser-side 7-day cache window.
     */
    private const LIST_CACHE_TTL = (3600 * 24 ) * 7;

    public function getList(string $type):JsonResponse
    {
        // 'accounts', 'customers', and 'items' are intentionally NOT cached: they embed live,
        // frequently-changing data (current_balance, inventory quantity) whose sources
        // don't bump the version, so caching would go stale.
        $dataList = match($type) {
            'warehouses' => $this->cached('warehouses', ['warehouses'], fn () => $this->warehouses(), true),
            'currencies' => $this->cached('currencies', ['currencies', 'currency_rate'], fn () => $this->currencies()),
            'users' => $this->cached('users', ['users'], fn () => $this->users()),

            // customers not cached — includes current_balance which changes on every sale/payment
            'customers' => $this->customers(),
            'customerPaymentTerms'  => $this->cached('customerPaymentTerms', ['customer_payment_terms'], fn () => $this->customerPaymentTerms()),
            'customerGroups'  => $this->cached('customerGroups', ['customer_groups'], fn () => $this->customerGroups()),
            'customerProvince'  => $this->cached('customerProvince', ['customer_province'], fn () => $this->customerProvince()),
            'customerType'  => $this->cached('customerType', ['customer_types'], fn () => $this->customerType()),
            'customerZone' => $this->cached('customerZone', ['customer_zone'], fn () => $this->customerZone()),

            // accounts (not cached — live current_balance)
            'accounts' => $this->accounts(),
            'accountTypes' => $this->cached('accountTypes', ['account_types'], fn () => $this->accountTypes()),

            // expenses
            'expenseCategories' => $this->cached('expenseCategories', ['expense_categories'], fn () => $this->expenseCategories()),
            'parentExpense' => $this->cached('parentExpense', ['expense_categories'], fn () => $this->parentExpense()),
            'purchaseExpenses' => $this->cached('purchaseExpenses', ['expense_categories'], fn () => $this->purchaseExpenses()),
            // incomes
            'incomeCategories' => $this->cached('incomeCategories', ['income_categories'], fn () => $this->incomeCategories()),
            'parentIncome' => $this->cached('parentIncome', ['income_categories'], fn () => $this->parentIncome()),

            // items (not cached — embeds live inventory quantity)
            'items' => $this->items(),
            'itemPriceLists' => $this->cached('itemPriceLists', ['price_lists', 'items'], fn () => $this->itemPriceLists()),
            'itemDefaultPriceList' => $this->cached('itemDefaultPriceList', ['price_lists', 'items'], fn () => $this->itemDefaultPriceList()),
            'itemBrands' => $this->cached('itemBrands', ['item_brands'], fn () => $this->itemBrands()),
            'itemCategories' => $this->cached('itemCategories', ['item_categories'], fn () => $this->itemCategories()),
            'itemFamilies' => $this->cached('itemFamilies', ['item_families'], fn () => $this->itemFamilies()),
            'itemGroups' => $this->cached('itemGroups', ['item_groups'], fn () => $this->itemGroups()),
            'itemProfitMargins' => $this->cached('itemProfitMargins', ['item_profits_margins'], fn () => $this->itemProfitMargins()),
            'itemTypes' => $this->cached('itemTypes', ['item_types'], fn () => $this->itemTypes()),
            'itemUnits' => $this->cached('itemUnits', ['item_units'], fn () => $this->itemUnits()),

            // item offers (not cached — used_count changes on every use, validity is date-relative)
            'itemOffers' => $this->itemOffers(),

            // suppliers
            'suppliers'  => $this->cached('suppliers', ['suppliers'], fn () => $this->suppliers()),
            'supplierPaymentTerms' => $this->cached('supplierPaymentTerms', ['supplier_payment_terms'], fn () => $this->supplierPaymentTerms()),
            'supplierTypes' => $this->cached('supplierTypes', ['supplier_types'], fn () => $this->supplierTypes()),

            // employees
            'employees' => $this->cached('employees', ['employees', 'department'], fn () => $this->employees()),
            'commissionTargets' => $this->cached('commissionTargets', ['commission_targets'], fn () => $this->commissionTargets()),
            'all-sales-employees' => $this->cached('all-sales-employees', ['employees'], fn () => $this->salesEmployee()),
            'all-driver-employees' => $this->cached('all-driver-employees', ['employees'], fn () => $this->driverEmployee()),
            'departments' => $this->cached('departments', ['department'], fn () => $this->departments()),

            // vehicles
            'cars' => $this->cached('cars', ['cars'], fn () => $this->cars()),
            'gasStations' => $this->cached('gasStations', ['gas_stations'], fn () => $this->gasStations()),

            // generals
            'countries' => $this->cached('countries', ['countries'], fn () => $this->countries()),
            'taxCodes' => $this->cached('taxCodes', ['tax_codes'], fn () => $this->taxCodes()),


            default      => response()->json(['error' => 'Invalid list type'], 400),
        };
        return ApiResponse::index($type . ' data', $dataList);
    }

    /**
     * Wrap a list query in a version-keyed cache.
     *
     * The cache key embeds the current AttachCacheVersion version number(s) for
     * the given model group(s). When any of those models change, its version is
     * bumped and the key changes, so a fresh query runs and stale data is never
     * served. The cache is already tenant-isolated by PrefixCacheTask.
     *
     * @param  string    $type         List type (cache key namespace).
     * @param  string[]  $versionKeys  Version keys this list depends on.
     * @param  \Closure  $callback     Produces the data on cache miss.
     * @param  bool      $perUser      Scope the key to the authed user (role-filtered lists).
     */
    private function cached(string $type, array $versionKeys, \Closure $callback, bool $perUser = false)
    {
        $versions = AttachCacheVersion::getVersions();
        $versionSig = implode('.', array_map(fn ($k) => $versions[$k] ?? 0, $versionKeys));

        $key = "list:{$type}";
        if ($perUser) {
            $key .= ':u' . (Auth::id() ?? 0);
        }
        $key .= ':v' . $versionSig;

        return Cache::remember($key, self::LIST_CACHE_TTL, $callback);
    }

    // generals
    private function warehouses()
    {
        $query = Warehouse::active()->orderby('name');

        if (RoleHelper::isWarehouseManager()) {
            $employee = RoleHelper::getWarehouseEmployee();
            if ($employee) {
                $query->whereHas('employees', function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id);
                });
            }
        }

        return $query->get(['id', 'name', 'is_default', 'include_in_total_stock', 'is_available_for_sales', 'address_line_1']);
    }

    private function currencies()
    {
        return Currency::with('activeRate:id,currency_id,rate')->active()->orderby('name')->get(['id', 'name', 'code', 'symbol', 'calculation_type', 'symbol_position','decimal_places','decimal_separator', 'thousand_separator'])
        ;
    }

    // suppliers
    private function suppliers()
    {
        return Supplier::active()->orderBy('name')->get(['id', 'code', 'name', 'currency_id']);
    }

    //customers
    private function customers()
    {
        // Load price lists but NOT their items (would be 300k+ records!)
        // Price list items should be loaded separately when viewing a specific customer
        $query = Customer::with([
            'priceListINV:id,code,description,is_default_inv,is_default_inx',
            'priceListINX:id,code,description,is_default_inv,is_default_inx',
            'salesperson:id,name',
        ])->active()->orderby('name');

        if (RoleHelper::isSalesman()) {
            $employee = Employee::where('user_id', RoleHelper::authUser()->id )->first();

            if($employee){
                $query->where('salesperson_id', $employee->id);
            } else {
                return collect();
            }
        }

        return $query->get(['id', 'parent_id',
        'code',
        'name',
        // 'customer_type_id',
        // 'customer_group_id',
        // 'customer_province_id',
        // 'customer_zone_id',
        'price_list_id_INV',
        'price_list_id_INX',
        // 'opening_balance',
        'current_balance',
        // 'address',
        'city',
        // 'telephone',
        // 'mobile',
        // 'url',
        // 'email',
        // 'contact_name',
        // 'gps_coordinates',
        // 'mof_tax_number',
        'salesperson_id',
        'customer_payment_term_id',
        'discount_percentage',
        'credit_limit',
        // 'notes',
        // 'is_active'
     ]);
    }

    private function customerPaymentTerms()
    {
        return CustomerPaymentTerm::isActive()->orderBy('name')->get(['id', 'name', 'days']);
    }

    private function customerGroups()
    {
        return CustomerGroup::active()->orderBy('name')->get(['id', 'name']);
    }

    private function customerProvince()
    {
        return CustomerProvince::active()->orderBy('name')->get(['id', 'name']);
    }

    private function customerType()
    {
        return CustomerType::active()->orderBy('name')->get(['id', 'name']);
    }

    private function customerZone()
    {
        return CustomerZone::active()->orderBy('name')->get(['id', 'name']);
    }

    // accounts
    private function accounts()
    {
        $query = Account::with('currency:id,symbol,code,decimal_places,decimal_separator,calculation_type')->active();

        // Only include private accounts if user is super admin or developer
        if (!RoleHelper::canSuperAdmin()) {
            $query->where('is_private', false);
        }

        return $query->orderBy('name')->get(['id', 'name', 'currency_id', 'include_in_total', 'hide_from_transaction', 'current_balance']);
    }

    private function accountTypes()
    {
        return AccountType::active()->orderBy('name')->get(['id', 'name']);
    }

    // expenses
    private function expenseCategories()
    {
        return ExpenseCategory::active()->orderBy('name')->get(['id', 'name', 'parent_id']);
    }

    private function parentExpense()
    {
        return ExpenseCategory::active()->orderBy('name')->get(['id', 'name'])->isRoot();
    }

    // subcategories under the system "Purchase Expenses" parent
    private function purchaseExpenses()
    {
        $parent = ExpenseCategory::where('name', 'Purchase Expenses')
            ->where('is_system', true)
            ->firstOrFail();

        return ExpenseCategory::where('parent_id', $parent->id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);
    }

    // incomes
    private function incomeCategories()
    {
        return IncomeCategory::active()->orderBy('name')->get(['id', 'name', 'parent_id']);
    }

    private function parentIncome()
    {
        return IncomeCategory::active()->orderBy('name')->get(['id', 'name'])->isRoot();
    }

    // users
    private function users()
    {
        return User::active()->orderBy('name')->get(['id', 'name', 'email', 'role']);
    }
    
    // items

    private function items()
    {
        $with = ['itemUnit:id,name,short_name', 'itemPrice:id,item_id,price_usd', 'inventories:id,warehouse_id,item_id,quantity', 'taxCode:id,name,tax_percent'];
        return Item::with($with)->orderBy('description')->get(['id', 'description', 'code', 'short_name', 'item_unit_id', 'tax_code_id', 'is_active']);
    }

    private function itemPriceLists()
    {
        $with = ['priceListItems' => fn($q) => $q->select(['id', 'price_list_id', 'item_code', 'sell_price', 'item_description', 'item_id'])
            ->whereHas('item', fn($q) => $q->where('is_active', true))];

        return PriceList::with($with)->active()->orderBy('code')->get(['id', 'code', 'description', 'item_count', 'is_default_inv', 'is_default_inx']);
    }

    private function itemDefaultPriceList()
    {
        $fields = ['id', 'code', 'description', 'item_count', 'is_default_inv', 'is_default_inx'];
        $with = ['priceListItems' => fn($q) => $q->select(['id', 'price_list_id', 'item_code', 'sell_price', 'item_description', 'item_id'])
            ->whereHas('item', fn($q) => $q->where('is_active', true))];

        return [
            'inv' => PriceList::with($with)->select($fields)->defaultInv()->first(),
            'inx' => PriceList::with($with)->select($fields)->defaultInx()->first(),
        ];
    }

    public function itemWithParameter(array $files = [] , array $relations = ['tax_code']): JsonResponse
    {
        $defaultFields = ['id', 'description', 'code', 'short_name', 'item_unit_id'];
        $defaultRelations = ['itemUnit:id,name,short_name', 'itemPrice', 'inventories'];

        $fields = empty($files) ? $defaultFields : array_merge($defaultFields, $files);

        $with = $defaultRelations;

        if (!empty($relations)) {
            foreach ($relations as $relation) {
                switch ($relation) {
                    case 'type':
                        $with[] = 'itemType:id,name';
                        break;
                    case 'family':
                        $with[] = 'itemFamily:id,name';
                        break;
                    case 'group':
                        $with[] = 'itemGroup:id,name';
                        break;
                    case 'category':
                        $with[] = 'itemCategory:id,name';
                        break;
                    case 'brand':
                        $with[] = 'itemBrand:id,name';
                        break;
                    case 'profit_margin':
                        $with[] = 'itemProfitMargin:id,name';
                        break;
                    case 'supplier':
                        $with[] = 'supplier:id,code,name';
                        break;
                    case 'tax_code':
                        $fields[] = 'tax_code_id';
                        $with[] = 'taxCode:id,name,tax_percent';
                        break;
                    case 'created_by':
                        $with[] = 'createdBy:id,name';
                        break;
                    case 'updated_by':
                        $with[] = 'updatedBy:id,name';
                        break;
                    case 'documents':
                        $with[] = 'documents';
                        break;
                }
            }
        }

        $items = Item::select($fields)->with($with)->active()->get();
        return ApiResponse::index('item with parameter data', $items);
    }

    private function itemBrands()
    {
        return ItemBrand::active()->orderBy('name')->get(['id', 'name']);
    }

    private function itemCategories()
    {
        return ItemCategory::active()->orderBy('name')->get(['id', 'name']);
    }

    private function itemFamilies()
    {
        return ItemFamily::active()->orderBy('name')->get(['id', 'name']);
    }

    private function itemGroups()
    {
        return ItemGroup::active()->orderBy('name')->get(['id', 'name']);
    }

    private function itemProfitMargins()
    {
        return ItemProfitMargin::active()->orderBy('name')->get(['id', 'name', 'margin_percentage']);
    }

    private function itemTypes()
    {
        return ItemType::active()->orderBy('name')->get(['id', 'name']);
    }

    private function itemUnits()
    {
        return ItemUnit::active()->orderBy('name')->get(['id', 'name', 'short_name', 'description']);
    }

    private function itemOffers()
    {
        return ItemOffer::active()->valid()
            ->with([
                'item:id,description,code,short_name',
                'item.inventories:id,item_id,warehouse_id,quantity',
                'item.inventories.warehouse:id,name',
                'freeItem:id,description,code,short_name',
                'freeItem.inventories:id,item_id,warehouse_id,quantity',
                'freeItem.inventories.warehouse:id,name',
            ])
            ->orderBy('date', 'desc')
            ->get([
                'id',
                'item_id',
                'free_item_id',
                'date',
                'validity_date',
                'minimum_quantity',
                'free_quantity',
                'usage_limit',
                'can_change_quantity',
                'allow_multiple',
                'used_count',
            ]);
    }

    // supplier terms and types
    private function supplierPaymentTerms()
    {
        return SupplierPaymentTerm::active()->orderBy('name')->get(['id', 'name', 'days']);
    }

    private function supplierTypes()
    {
        return SupplierType::active()->orderBy('name')->get(['id', 'name']);
    }

    // employees
    private function employees()
    {
        return Employee::active()->with('department:id,name')->orderBy('name')->get(['id', 'base_salary', 'name', 'email', 'phone', 'department_id']);
    }

    private function commissionTargets()
    {
        return CommissionTarget::active()->with('rules')->orderBy('name')->get();
    }

    private function salesEmployee()
    {
        return Employee::isSaleDepartment()->orderBy('name')->get(['id', 'name', 'email', 'phone', 'is_active']);
    }

    private function driverEmployee()
    {
        return Employee::isDriverDepartment()->orderBy('name')->get(['id', 'name', 'email', 'phone', 'is_active']);
    }

    private function departments()
    {
        return Department::active()->orderBy('name')->get(['id', 'name']);
    }

    // vehicles
    private function cars()
    {
        return Car::where('is_active', true)->orderBy('name')->get(['id', 'name', 'plate_number']);
    }

    private function gasStations()
    {
        return GasStation::where('is_active', true)->orderBy('name')->get(['id', 'name', 'address']);
    }

    // generals
    private function countries()
    {
        return Country::active()->orderBy('name')->get(['id', 'name', 'code']);
    }

    private function taxCodes()
    {
        return TaxCode::active()->orderBy('name')->get(['id', 'name', 'tax_percent']);
    }
}
