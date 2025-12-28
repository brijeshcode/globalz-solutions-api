<?php

namespace App\Http\Controllers\Api;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Models\Customers\Customer;
use App\Models\Employees\CommissionTarget;
use App\Models\Employees\Employee;
use App\Models\Items\Item;
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
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListDataController extends Controller
{
    public function getList(string $type):JsonResponse
    {
        $dataList = match($type) {
            'warehouses' => $this->warehouses(),
            'currencies' => $this->currencies(),
            'users' => $this->users(),
            
            'customers' => $this->customers(),
            'customerPaymentTerms'  => $this->customerPaymentTerms(),
            'customerGroups'  => $this->customerGroups(),
            'customerProvince'  => $this->customerProvince(),
            'customerType'  => $this->customerType(),
            'customerZone' => $this->customerZone(),

            // accounts
            'accounts' => $this->accounts(),
            'accountTypes' => $this->accountTypes(),

            // expenses
            'expenseCategories' => $this->expenseCategories(),

            // expenses
            'incomeCategories' => $this->incomeCategories(),

            // items
            'items' => $this->items(),
            'itemPriceLists' => $this->itemPriceLists(),
            'itemDefaultPriceList' => $this->itemDefaultPriceList(),
            'itemBrands' => $this->itemBrands(),
            'itemCategories' => $this->itemCategories(),
            'itemFamilies' => $this->itemFamilies(),
            'itemGroups' => $this->itemGroups(),
            'itemProfitMargins' => $this->itemProfitMargins(),
            'itemTypes' => $this->itemTypes(),
            'itemUnits' => $this->itemUnits(),

            // suppliers
            'suppliers'  => $this->suppliers(),
            'supplierPaymentTerms' => $this->supplierPaymentTerms(),
            'supplierTypes' => $this->supplierTypes(),

            // employees
            'employees' => $this->employees(),
            'commissionTargets' => $this->commissionTargets(),
            'sales-employees' => $this->salesEmployee(),
            'departments' => $this->departments(),

            // generals
            'countries' => $this->countries(),
            'taxCodes' => $this->taxCodes(),

            
            default      => response()->json(['error' => 'Invalid list type'], 400),
        };
        return ApiResponse::index($type . ' data', $dataList);
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

        return $query->get(['id', 'name', 'is_default', 'address_line_1']);
    }

    private function currencies()
    {
        return Currency::with('activeRate:id,currency_id,rate')->active()->orderby('name')->get(['id', 'name', 'code', 'symbol', 'calculation_type', 'symbol_position','decimal_places','decimal_separator', 'thousand_separator'])
        ;
    }

    // suppliers
    private function suppliers()
    {
        return Supplier::active()->orderby('name')->get(['id', 'code', 'name']);
    }

    //customers
    private function customers()
    {
        // Load price lists but NOT their items (would be 300k+ records!)
        // Price list items should be loaded separately when viewing a specific customer
        $query = Customer::with([
            'priceListINV:id,code,description,is_default',
            'priceListINX:id,code,description,is_default',
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
        return Account::with('currency:id,symbol,code,decimal_places,decimal_separator,calculation_type')->active()->orderBy('name')->get(['id', 'name', 'currency_id']);
    }

    private function accountTypes()
    {
        return AccountType::active()->orderBy('name')->get(['id', 'name']);
    }

    // expenses
    private function expenseCategories()
    {
        return ExpenseCategory::active()->orderBy('name')->get(['id', 'name']);
    }

    // incomes
    private function incomeCategories()
    {
        return IncomeCategory::active()->orderBy('name')->get(['id', 'name']);
    }

    // users
    private function users()
    {
        return User::active()->orderBy('name')->get(['id', 'name', 'email', 'role']);
    }
    
    // items

    private function items()
    {
        $with = ['itemUnit:id,name,short_name', 'itemPrice:id,item_id,price_usd', 'inventories:id,warehouse_id,item_id,quantity'];
        return Item::with($with)->orderBy('description')->active()->get(['id', 'description', 'code', 'short_name', 'item_unit_id']);
    }

    private function itemPriceLists()
    {
        return PriceList::with('priceListItems:id,price_list_id,item_code,sell_price,item_description,item_id')->orderBy('code')->get(['id', 'code', 'description', 'item_count', 'is_default']);
    }

    private function itemDefaultPriceList()
    {
        return PriceList::with('priceListItems:id,price_list_id,item_code,sell_price,item_description,item_id')->select('id', 'code', 'description', 'item_count', 'is_default')->default()->first();
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
        return Employee::active()->isSaleDepartment()->orderBy('name')->get(['id', 'name', 'email', 'phone']);
    }

    private function departments()
    {
        return Department::active()->orderBy('name')->get(['id', 'name']);
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
