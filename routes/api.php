<?php

use App\Http\Controllers\Api\Accounts\AccountsController;
use App\Http\Controllers\Api\Accounts\AccountStatementController;
use App\Http\Controllers\Api\Accounts\AccountTransfersController;
use App\Http\Controllers\Api\Accounts\AccountAdjustsController;
use App\Http\Controllers\Api\Accounts\IncomeTransactionsController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\LoginLogsController;
use App\Http\Controllers\Api\HomePageController;
use App\Http\Controllers\Api\Customers\CustomersController;
use App\Http\Controllers\Api\Customers\CustomerCreditDebitNotesController;
use App\Http\Controllers\Api\Customers\CustomerPaymentsController;
use App\Http\Controllers\Api\Customers\CustomerPaymentOrdersController;
use App\Http\Controllers\Api\Customers\CustomerReturnsController;
use App\Http\Controllers\Api\Customers\CustomerReturnOrdersController;
use App\Http\Controllers\Api\Customers\CustomerStatmentController;
use App\Http\Controllers\Api\Customers\SalesController;
use App\Http\Controllers\Api\Customers\SaleOrdersController;
use App\Http\Controllers\Api\Setups\ItemBrandsController;
use App\Http\Controllers\Api\Setups\ItemCategoriesController;
use App\Http\Controllers\Api\Setups\ItemFamiliesController;
use App\Http\Controllers\Api\Setups\ItemGroupsController;
use App\Http\Controllers\Api\Setups\ItemProfitMarginsController;
use App\Http\Controllers\Api\Setups\ItemTypesController;
use App\Http\Controllers\Api\Setups\ItemUnitController;
use App\Http\Controllers\Api\Setups\SupplierTypesController;
use App\Http\Controllers\Api\Setups\Customers\CustomerTypesController;
use App\Http\Controllers\Api\Setups\WarehousesController;
use App\Http\Controllers\Api\Setups\CountriesController;
use App\Http\Controllers\Api\Setups\SupplierPaymentTermsController;
use App\Http\Controllers\Api\Setups\TaxCodesController;
use App\Http\Controllers\Api\Items\ItemsController;
use App\Http\Controllers\Api\Items\ItemTransfersController;
use App\Http\Controllers\Api\Items\ItemAdjustsController;
use App\Http\Controllers\Api\Items\ItemMovementsController;
use App\Http\Controllers\Api\Items\ItemCostHistoryController;
use App\Http\Controllers\Api\Items\PriceListsController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\Setups\Customers\CustomerGroupsController;
use App\Http\Controllers\Api\Setups\Customers\CustomerPaymentTermsController;
use App\Http\Controllers\Api\Setups\Customers\CustomerProvincesController;
use App\Http\Controllers\Api\Setups\Customers\CustomerZonesController;
use App\Http\Controllers\Api\Setups\Employees\DepartmentsController;
use App\Http\Controllers\Api\Setups\Users\UsersController;
use App\Http\Controllers\Api\Setups\Expenses\ExpenseCategoriesController;
use App\Http\Controllers\Api\Expenses\ExpenseTransactionsController;
use App\Http\Controllers\Api\Employees\EmployeesController;
use App\Http\Controllers\Api\Employees\AdvanceLoansController;
use App\Http\Controllers\Api\Employees\CommissionTargetsController;
use App\Http\Controllers\Api\ListDataController;
use App\Http\Controllers\Api\Setups\Accounts\AccountTypesController;
use App\Http\Controllers\Api\Setups\Generals\CompanyController;
use App\Http\Controllers\Api\Setups\Generals\Currencies\CurrenciesController;
use App\Http\Controllers\Api\Setups\Generals\Currencies\currencyRatesController;
use App\Http\Controllers\Api\Suppliers\PurchasesController;
use App\Http\Controllers\Api\Suppliers\PurchaseReturnsController;
use App\Http\Controllers\Api\Suppliers\SupplierCreditDebitNotesController;
use App\Http\Controllers\Api\Suppliers\SupplierPaymentsController;
use App\Http\Controllers\Api\Suppliers\SupplierStatmentController;
use App\Http\Controllers\Api\Suppliers\SuppliersController;
use App\Http\Controllers\Api\ClearDataController;
use App\Http\Controllers\Api\Employees\EmployeeCommissionsController;
use App\Http\Controllers\Api\Employees\SalaryController;
use App\Http\Controllers\Api\Setups\Accounts\IncomeCategoriesController;
use App\Http\Controllers\Api\Setups\Customers\ImportCustomerSetupController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

// Public routes (no authentication required)
Route::get('/documents/{document}/preview-signed', [DocumentController::class, 'previewSigned'])
    ->name('documents.preview-signed');

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/unlock-ip', [AuthController::class, 'unlockIp']);

    // Login Logs
    Route::get('/login-logs', [LoginLogsController::class, 'index'])->name('login-logs.index');

    Route::get('/homepage', [HomePageController::class, 'HomePage'])->name('homepage');

    Route::get('/list-data/{type}', [ListDataController::class, 'getList'])->name('getList');
    Route::get('/list-data/items-with-parameters', [ListDataController::class, 'itemWithParameter'])->name('itemWithParameter');

    // Employees Controller
    Route::controller(EmployeesController::class)->prefix('employees')->name('employees.')->group(function () {
        Route::get('trashed', 'trashed')->name('trashed');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{employee}', 'show')->name('show');
        Route::put('{employee}', 'update')->name('update');
        Route::delete('{employee}', 'destroy')->name('destroy');
        Route::patch('{employee}/assign-warehouse', 'assignWarehouse')->name('assignWarehouse');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        Route::post('commission-setup', 'setCommissionTarget')->name('commission-setup');
        Route::get('employee-commissions', 'getEmployeeCommissionTarget')->name('employee-commissions');
    });

    // AdvanceLoans Controller
    Route::controller(AdvanceLoansController::class)->prefix('advanceLoans')->name('advanceLoans.')->group(function () {
        Route::get('stats', 'stats')->name('stats');
        Route::get('trashed', 'trashed')->name('trashed');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{advanceLoan}', 'show')->name('show');
        Route::put('{advanceLoan}', 'update')->name('update');
        Route::delete('{advanceLoan}', 'destroy')->name('destroy');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
    });

    // Salaries Controller
    Route::controller(SalaryController::class)->prefix('salaries')->name('salaries.')->group(function () {
        Route::get('stats', 'stats')->name('stats');
        Route::get('trashed', 'trashed')->name('trashed');
        Route::get('pending-loans/{employeeId}', 'getPendingLoans')->name('pendingLoans');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('my', 'mySalaries')->name('mySalaries');
        Route::get('{salary}', 'show')->name('show');
        Route::get('my/show/{salary}', 'mySalaryDetail')->name('mySalaryDetail');
        Route::put('{salary}', 'update')->name('update');
        Route::delete('{salary}', 'destroy')->name('destroy');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
    });

    Route::controller(EmployeeCommissionsController::class)->prefix('employee/business')->name('employee.business.')->group(function () {
        Route::get('monthly/commission', 'getMonthlyCommission')->name('getMonthlyCommission');
        Route::get('my-monthly/commission', 'getEmployeeMonthlyCommission')->name('myMonthlyCommission');
    });

    // Commission Targets Controller
    Route::controller(CommissionTargetsController::class)->prefix('commission-targets')->name('commission-targets.')->group(function () {
        Route::get('stats', 'stats')->name('stats');
        Route::get('trashed', 'trashed')->name('trashed');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{commissionTarget}', 'show')->name('show');
        Route::put('{commissionTarget}', 'update')->name('update');
        Route::delete('{commissionTarget}', 'destroy')->name('destroy');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
    });

    Route::prefix('customers')->name('customers.')->group(function () {

        // Customer Statements Controller - Must be defined BEFORE {customer} routes to avoid conflicts
        Route::controller(CustomerStatmentController::class)->prefix('statements')->name('statements.')->group(function () {
            Route::get('/', 'statements')->name('index');
            Route::get('{customer}', 'customerStatements')->name('customer');
        });

        // Sales Controller (for approved sales) - Must be defined BEFORE {customer} routes to avoid conflicts
        Route::controller(SalesController::class)->prefix('sales')->name('sales.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{sale}', 'show')->name('show');
            Route::put('{sale}', 'update')->name('update');
            Route::delete('{sale}', 'destroy')->name('destroy');
            Route::patch('{sale}/changeStatus', 'changeStatus')->name('changeStatus');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Sale Orders Controller (for pending sale orders) - Must be defined BEFORE {customer} routes to avoid conflicts
        Route::controller(SaleOrdersController::class)->prefix('sale-orders')->name('sale-orders.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{sale}', 'show')->name('show');
            Route::put('{sale}', 'update')->name('update');
            Route::delete('{sale}', 'destroy')->name('destroy');
            Route::patch('{sale}/approve', 'approve')->name('approve');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Customer Payments Controller (for approved payments) - Must be defined BEFORE {customer} routes to avoid conflicts
        Route::controller(CustomerPaymentsController::class)->prefix('payments')->name('payments.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{customerPayment}', 'show')->name('show');
            Route::put('{customerPayment}', 'update')->name('update');
            Route::delete('{customerPayment}', 'destroy')->name('destroy');
            Route::patch('{customerPayment}/unapprove', 'unapprove')->name('unapprove');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Customer Payment Orders Controller (for pending payment orders) - Must be defined BEFORE {customer} routes to avoid conflicts
        Route::controller(CustomerPaymentOrdersController::class)->prefix('payment-orders')->name('payment-orders.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{customerPayment}', 'show')->name('show');
            Route::put('{customerPayment}', 'update')->name('update');
            Route::delete('{customerPayment}', 'destroy')->name('destroy');
            Route::patch('{customerPayment}/approve', 'approve')->name('approve');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Customer Returns Controller (for approved returns) - Must be defined BEFORE {customer} routes to avoid conflicts
        Route::controller(CustomerReturnsController::class)->prefix('returns')->name('returns.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{customerReturn}', 'show')->name('show');
            Route::put('{customerReturn}', 'update')->name('update');
            Route::delete('{customerReturn}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            Route::patch('{customerReturn}/mark-received', 'markReceived')->name('markReceived');

        });

        // Customer Return Orders Controller (for pending return orders) - Must be defined BEFORE {customer} routes to avoid conflicts
        Route::controller(CustomerReturnOrdersController::class)->prefix('return-orders')->name('return-orders.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('sale-items-for-return', 'getSaleItemsForReturn')->name('sale-items-for-return');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{customerReturn}', 'show')->name('show');
            Route::put('{customerReturn}', 'update')->name('update');
            Route::delete('{customerReturn}', 'destroy')->name('destroy');
            Route::patch('{customerReturn}/approve', 'approve')->name('approve');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Customer Credit/Debit Notes Controller - Must be defined BEFORE {customer} routes to avoid conflicts
        Route::controller(CustomerCreditDebitNotesController::class)->prefix('credit-debit-notes')->name('credit-debit-notes.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{customerCreditDebitNote}', 'show')->name('show');
            Route::put('{customerCreditDebitNote}', 'update')->name('update');
            Route::delete('{customerCreditDebitNote}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        Route::controller(CustomersController::class)->group(function () {

            Route::get('stats', 'stats')->name('stats');
            Route::get('export', 'export')->name('export');
            Route::get('import-template', 'downloadTemplate')->name('import-template');
            Route::post('import', 'import')->name('import');
            Route::get('next-code', 'getNextCode')->name('next-code');
            Route::get('salespersons', 'getSalespersons')->name('salespersons');
            Route::get('trashed', 'trashed')->name('trashed');
            // this is pause due to code refactore, we are removing customerbalanceservice and refineing the logic
            Route::post('refresh-balances', 'recalculateBalances')->name('refresh-balances'); 
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{customer}', 'show')->name('show');
            Route::put('{customer}', 'update')->name('update');
            Route::delete('{customer}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });
    });


    Route::prefix('accounts')->name('accounts.')->group(function () {

        // Account Statements Controller - Must be defined BEFORE {account} routes to avoid conflicts
        Route::controller(AccountStatementController::class)->prefix('statements')->name('statements.')->group(function () {
            Route::get('/', 'statements')->name('index');
            Route::get('{account}', 'accountStatements')->name('account');
        });

        // Account Transfers Controller - Must be defined BEFORE {account} routes to avoid conflicts
        Route::controller(AccountTransfersController::class)->prefix('transfers')->name('transfers.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{accountTransfer}', 'show')->name('show');
            Route::put('{accountTransfer}', 'update')->name('update');
            Route::delete('{accountTransfer}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Account Adjusts Controller (Admin only) - Must be defined BEFORE {account} routes to avoid conflicts
        Route::controller(AccountAdjustsController::class)->prefix('adjusts')->name('adjusts.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{accountAdjust}', 'show')->name('show');
            Route::put('{accountAdjust}', 'update')->name('update');
            Route::delete('{accountAdjust}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Accounts Controller
        Route::controller(AccountsController::class)->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{account}', 'show')->name('show');
            Route::put('{account}', 'update')->name('update');
            Route::delete('{account}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });
    });

    Route::prefix('suppliers')->name('suppliers.')->group(function () {

        // Supplier Statements Controller - Must be defined BEFORE {supplier} routes to avoid conflicts
        Route::controller(SupplierStatmentController::class)->prefix('statements')->name('statements.')->group(function () {
            // Route::get('/', 'statements')->name('index');
            Route::get('{supplier}', 'supplierStatements')->name('supplier');
        });

        Route::controller(PurchasesController::class)->prefix('purchases')->name('purchases.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{purchase}', 'show')->name('show');
            Route::put('{purchase}', 'update')->name('update');
            Route::patch('{purchase}/changeStatus', 'changeStatus')->name('changeStatus');
            Route::delete('{purchase}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        Route::controller(PurchaseReturnsController::class)->prefix('purchase-returns')->name('purchase-returns.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{purchaseReturn}', 'show')->name('show');
            Route::put('{purchaseReturn}', 'update')->name('update');
            Route::delete('{purchaseReturn}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::patch('{purchaseReturn}/changeStatus', 'changeStatus')->name('changeStatus');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            Route::post('{purchaseReturn}/documents', 'uploadDocuments')->name('documents.upload');
            Route::delete('{purchaseReturn}/documents', 'deleteDocuments')->name('documents.delete');
            Route::get('{purchaseReturn}/documents', 'getDocuments')->name('documents.index');
        });

        // Supplier Credit/Debit Notes Controller - Must be defined BEFORE {supplier} routes to avoid conflicts
        Route::controller(SupplierCreditDebitNotesController::class)->prefix('credit-debit-notes')->name('credit-debit-notes.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{supplierCreditDebitNote}', 'show')->name('show');
            Route::put('{supplierCreditDebitNote}', 'update')->name('update');
            Route::delete('{supplierCreditDebitNote}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Supplier Payments Controller - Must be defined BEFORE {supplier} routes to avoid conflicts
        Route::controller(SupplierPaymentsController::class)->prefix('payments')->name('payments.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{supplierPayment}', 'show')->name('show');
            Route::put('{supplierPayment}', 'update')->name('update');
            Route::delete('{supplierPayment}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

    });

    Route::prefix('items')->name('items.')->group(function () {

        // Item Movements Controller - Must be defined BEFORE other item routes
        Route::controller(ItemMovementsController::class)->prefix('movements')->name('movements.')->group(function () {
            Route::get('/', 'index')->name('index');
        });

        // Item Cost History Controller
        Route::controller(ItemCostHistoryController::class)->prefix('cost-history')->name('cost-history.')->group(function () {
            Route::get('/', 'index')->name('index');
        });

        // Item Transfers Controller
        Route::controller(ItemTransfersController::class)->prefix('transfers')->name('transfers.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{itemTransfer}', 'show')->name('show');
            Route::put('{itemTransfer}', 'update')->name('update');
            Route::delete('{itemTransfer}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Item Adjusts Controller (Admin only)
        Route::controller(ItemAdjustsController::class)->prefix('adjusts')->name('adjusts.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{itemAdjust}', 'show')->name('show');
            Route::put('{itemAdjust}', 'update')->name('update');
            Route::delete('{itemAdjust}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

    });

    Route::prefix('setups')->name('setups.')->group(function () {
        
        Route::prefix('customers')->middleware(['auth'])->group(function () {
            Route::post('/import', [ImportCustomerSetupController::class, 'import'])->name('customer.import');
            // Route::get('/settings/template', [ImportCustomerSetupController::class, 'downloadTemplate'])->name('settings.template');
        });

        Route::controller(CompanyController::class)->prefix('company')->name('company.')->group(function () {
            Route::get('/', 'get')->name('get');
            Route::post('/getSelected', 'getSelected')->name('getSelected');
            Route::post('/', 'set')->name('set');
        });

        // Warehouses Controller
        Route::controller(WarehousesController::class)->prefix('warehouses')->name('warehouses.')->group(function () {
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{warehouse}', 'show')->name('show');
            Route::put('{warehouse}', 'update')->name('update');
            Route::delete('{warehouse}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::patch('{id}/setdefault', 'setDefault')->name('setDefault');

            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Currencies Controller
        Route::prefix('currencies')->name('currencies.')->group(function () {
            Route::controller(CurrenciesController::class)->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{currency}', 'show')->name('show');
                Route::put('{currency}', 'update')->name('update');
                Route::delete('{currency}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            Route::controller(currencyRatesController::class)->prefix('rates')->name('rates.')->group(function () {
                Route::post('change', 'changeRate')->name('change');
                Route::get('{currency}/history', 'index')->name('history');
            });

        });

        // Countries Controller
        Route::controller(CountriesController::class)->prefix('countries')->name('countries.')->group(function () {
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{country}', 'show')->name('show');
            Route::put('{country}', 'update')->name('update');
            Route::delete('{country}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Supplier Types Controller
        Route::controller(SupplierTypesController::class)->prefix('supplier-types')->name('supplier-types.')->group(function () {
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{supplierType}', 'show')->name('show');
            Route::put('{supplierType}', 'update')->name('update');
            Route::delete('{supplierType}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Customer Types Controller
        Route::prefix('customers')->name('customers.')->group(function() {

            Route::controller(CustomerTypesController::class)->prefix('types')->name('types.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{customerType}', 'show')->name('show');
                Route::put('{customerType}', 'update')->name('update');
                Route::delete('{customerType}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            Route::controller(CustomerZonesController::class)->prefix('zones')->name('zones.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{customerZone}', 'show')->name('show');
                Route::put('{customerZone}', 'update')->name('update');
                Route::delete('{customerZone}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            Route::controller(CustomerProvincesController::class)->prefix('provinces')->name('provinces.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{customerProvince}', 'show')->name('show');
                Route::put('{customerProvince}', 'update')->name('update');
                Route::delete('{customerProvince}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            Route::controller(CustomerGroupsController::class)->prefix('groups')->name('groups.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{customerGroup}', 'show')->name('show');
                Route::put('{customerGroup}', 'update')->name('update');
                Route::delete('{customerGroup}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            Route::controller(CustomerPaymentTermsController::class)->prefix('payment-terms')->name('paymentTerms.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{customerPaymentTerm}', 'show')->name('show');
                Route::put('{customerPaymentTerm}', 'update')->name('update');
                Route::delete('{customerPaymentTerm}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });
        });

        Route::prefix('employees')->name('employees.')->group(function() {

            Route::controller(DepartmentsController::class)->prefix('departments')->name('departments.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{department}', 'show')->name('show');
                Route::put('{department}', 'update')->name('update');
                Route::delete('{department}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });
        });

        Route::prefix('accounts')->name('accounts.')->group(function() {
            Route::controller(AccountTypesController::class)->prefix('types')->name('types.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{accountType}', 'show')->name('show');
                Route::put('{accountType}', 'update')->name('update');
                Route::delete('{accountType}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });
        });
        // Users Controller
        Route::controller(UsersController::class)->prefix('users')->name('users.')->group(function () {
            Route::get('unassigned-employees', 'getUnassignedEmployees')->name('unassigned-employees');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('{user}/status', 'status')->name('status');
            Route::get('{user}', 'show')->name('show');
            Route::put('{user}', 'update')->name('update');
            Route::delete('{user}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        
            
        // Supplier Payment Term Controller
        Route::controller(SupplierPaymentTermsController::class)->prefix('supplier-payment-terms')->name('supplierPaymentTerm.')->group(function () {
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{supplierPaymentTerm}', 'show')->name('show');
            Route::put('{supplierPaymentTerm}', 'update')->name('update');
            Route::delete('{supplierPaymentTerm}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        //suppliers 
        Route::controller(SuppliersController::class)->prefix('suppliers')->name('suppliers.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('export', 'export')->name('export');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{supplier}', 'show')->name('show');
            Route::put('{supplier}', 'update')->name('update');
            Route::delete('{supplier}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Tax Codes Controller
        Route::controller(TaxCodesController::class)->prefix('tax-codes')->name('tax-codes.')->group(function () {
            Route::get('active', 'active')->name('active');
            Route::get('default', 'getDefault')->name('default');
            Route::post('bulk-destroy', 'bulkDestroy')->name('bulk-destroy');
            Route::post('{taxCode}/calculate-tax', 'calculateTax')->name('calculate-tax');
            Route::patch('{taxCode}/set-default', 'setDefault')->name('set-default');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{taxCode}', 'show')->name('show');
            Route::put('{taxCode}', 'update')->name('update');
            Route::delete('{taxCode}', 'destroy')->name('destroy');
        });

        Route::prefix('items')->name('items.')->group(function () {

            // Item Units Controller
            Route::controller(ItemUnitController::class)->prefix('units')->name('units.')->group(function () {
                Route::get('active', 'active')->name('active');
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{unit}', 'show')->name('show');
                Route::put('{unit}', 'update')->name('update');
                Route::delete('{unit}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Types Controller
            Route::controller(ItemTypesController::class)->prefix('types')->name('types.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{type}', 'show')->name('show');
                Route::put('{type}', 'update')->name('update');
                Route::delete('{type}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Families Controller
            Route::controller(ItemFamiliesController::class)->prefix('families')->name('families.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{family}', 'show')->name('show');
                Route::put('{family}', 'update')->name('update');
                Route::delete('{family}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Groups Controller
            Route::controller(ItemGroupsController::class)->prefix('groups')->name('groups.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{group}', 'show')->name('show');
                Route::put('{group}', 'update')->name('update');
                Route::delete('{group}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Brands Controller
            Route::controller(ItemBrandsController::class)->prefix('brands')->name('brands.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{brand}', 'show')->name('show');
                Route::put('{brand}', 'update')->name('update');
                Route::delete('{brand}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Categories Controller
            Route::controller(ItemCategoriesController::class)->prefix('categories')->name('categories.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{category}', 'show')->name('show');
                Route::put('{category}', 'update')->name('update');
                Route::delete('{category}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Profit Margins Controller
            Route::controller(ItemProfitMarginsController::class)->prefix('profit-margins')->name('profit-margins.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{margin}', 'show')->name('show');
                Route::put('{margin}', 'update')->name('update');
                Route::delete('{margin}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

        });

        // Items Controller
        Route::controller(ItemsController::class)->prefix('items')->name('items.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('export', 'export')->name('export');
            Route::get('import-template', 'downloadTemplate')->name('import-template');
            Route::post('import', 'import')->name('import');
            Route::get('all', 'getAllItems')->name('all');
            Route::get('next-code', 'getNextCode')->name('next-code');
            Route::post('check-code', 'checkCodeAvailability')->name('check-code');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{item}', 'show')->name('show');
            Route::put('{item}', 'update')->name('update');
            Route::delete('{item}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });

        // Price Lists Controller
        Route::controller(PriceListsController::class)->prefix('price-lists')->name('price-lists.')->group(function () {
            Route::get('stats', 'stats')->name('stats');
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{priceList}', 'show')->name('show');
            Route::put('{priceList}', 'update')->name('update');
            Route::delete('{priceList}', 'destroy')->name('destroy');
            Route::post('{priceList}/duplicate', 'duplicate')->name('duplicate');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            Route::patch('{priceList}/setdefault', 'setDefault')->name('setDefault');

            // Price List Items Management
            Route::get('{priceList}/items', 'getItems')->name('items.index');
            Route::post('{priceList}/items', 'addItem')->name('items.store');
            Route::put('{priceList}/items/{priceListItem}', 'updateItem')->name('items.update');
            Route::delete('items/{priceListItem}', 'deleteItem')->name('items.delete');
        });

        Route::prefix('incomes')->name('incomes.')->group(function() {
            
            // Income Categories Controller
            Route::controller(IncomeCategoriesController::class)->prefix('categories')->name('categories.')->group(function () {
                Route::get('roots', 'roots')->name('roots');
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{incomeCategory}', 'show')->name('show');
                Route::get('{incomeCategory}/children', 'children')->name('children');
                Route::get('{incomeCategory}/ancestors', 'ancestors')->name('ancestors');
                Route::get('{incomeCategory}/descendants', 'descendants')->name('descendants');
                Route::get('{incomeCategory}/tree', 'tree')->name('tree');
                Route::patch('{incomeCategory}/move', 'move')->name('move');
                Route::put('{incomeCategory}', 'update')->name('update');
                Route::delete('{incomeCategory}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });
        });
        
        Route::prefix('expenses')->name('expenses.')->group(function() {
            
            // Expense Categories Controller
            Route::controller(ExpenseCategoriesController::class)->prefix('categories')->name('categories.')->group(function () {
                Route::get('roots', 'roots')->name('roots');
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{expenseCategory}', 'show')->name('show');
                Route::get('{expenseCategory}/children', 'children')->name('children');
                Route::get('{expenseCategory}/ancestors', 'ancestors')->name('ancestors');
                Route::get('{expenseCategory}/descendants', 'descendants')->name('descendants');
                Route::get('{expenseCategory}/tree', 'tree')->name('tree');
                Route::patch('{expenseCategory}/move', 'move')->name('move');
                Route::put('{expenseCategory}', 'update')->name('update');
                Route::delete('{expenseCategory}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });
        });
    });

    // Expense Transactions Controller
    Route::controller(ExpenseTransactionsController::class)->prefix('expense-transactions')->name('expense-transactions.')->group(function () {
        Route::get('trashed', 'trashed')->name('trashed');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{expenseTransaction}', 'show')->name('show');
        Route::put('{expenseTransaction}', 'update')->name('update');
        Route::delete('{expenseTransaction}', 'destroy')->name('destroy');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');

    });

    // income transactions controller
    Route::controller(IncomeTransactionsController::class)->prefix('income-transactions')->name('income-transactions.')->group(function () {
        Route::get('trashed', 'trashed')->name('trashed');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{incomeTransaction}', 'show')->name('show');
        Route::put('{incomeTransaction}', 'update')->name('update');
        Route::delete('{incomeTransaction}', 'destroy')->name('destroy');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');

    });

    // Documents Controller - Global document management
    Route::controller(DocumentController::class)->prefix('documents')->name('documents.')->group(function () {
        Route::get('stats', 'stats')->name('stats');
        Route::get('model-documents', 'getModelDocuments')->name('model-documents');
        Route::post('bulk-destroy', 'bulkDestroy')->name('bulk-destroy');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::delete('{id}/force-destroy', 'forceDestroy')->name('force-destroy');
        Route::get('{document}/download', 'download')->name('download');
        Route::get('{document}/preview', 'preview')->name('preview');
        Route::get('{document}/preview-url', 'getPreviewUrl')->name('preview-url');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{document}', 'show')->name('show');
        Route::put('{document}', 'update')->name('update');
        Route::delete('{document}', 'destroy')->name('destroy');
    });

    // Activity Log Controller - Audit trail management
    Route::controller(ActivityLogController::class)->prefix('activity-logs')->name('activity-logs.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{id}', 'show')->name('show');
        Route::post('model-activity', 'getModelActivity')->name('model-activity');
        Route::get('sales/{saleId}', 'getSaleActivity')->name('sales.activity');
    });

    // Clear Data Controller - System data management
    Route::controller(ClearDataController::class)->prefix('clear-data')->name('clear-data.')->group(function () {
        Route::delete('items', 'clearItems')->name('items');
        Route::delete('customers', 'clearCustomers')->name('customers');
        Route::delete('sales', 'clearSales')->name('sales');
        Route::delete('all', 'clearAll')->name('all');
    });

    // Reports - Business Intelligence & Analytics
    Route::prefix('reports')->name('reports.')->group(function () {

        // Sales Reports
        Route::prefix('sales')->name('sales.')->group(function () {
            Route::get('category-sales', [\App\Http\Controllers\Api\Reports\Sales\CategorySalesReportController::class, 'index'])->name('category-sales');
        });
    });
});