<?php

use App\Http\Controllers\Api\Accounts\AccountsController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Customers\CustomersController;
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
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\Setups\Customers\CustomerGroupsController;
use App\Http\Controllers\Api\Setups\Customers\CustomerPaymentTermsController;
use App\Http\Controllers\Api\Setups\Customers\CustomerProvincesController;
use App\Http\Controllers\Api\Setups\Customers\CustomerZonesController;
use App\Http\Controllers\Api\Setups\Employees\DepartmentsController;
use App\Http\Controllers\Api\Setups\Users\UsersController;
use App\Http\Controllers\Api\Setups\Expenses\ExpenseCategoriesController;
use App\Http\Controllers\Api\Expenses\ExpenseTransactionsController;
use App\Http\Controllers\Api\Employees\EmployeesController;
use App\Http\Controllers\Api\Setups\Accounts\AccountTypesController;
use App\Http\Controllers\Api\Setups\Generals\Currencies\CurrenciesController;
use App\Http\Controllers\Api\Setups\Generals\Currencies\currencyRatesController;
use App\Http\Controllers\Api\Suppliers\PurchasesController;
use App\Http\Controllers\Api\Suppliers\SuppliersController;
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

    // Employees Controller
    Route::controller(EmployeesController::class)->prefix('employees')->name('employees.')->group(function () {
        Route::get('trashed', 'trashed')->name('trashed');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{employee}', 'show')->name('show');
        Route::put('{employee}', 'update')->name('update');
        Route::delete('{employee}', 'destroy')->name('destroy');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
    });

    Route::controller(CustomersController::class)->prefix('customers')->name('customers.')->group(function () {
        Route::get('stats', 'stats')->name('stats');
        Route::get('export', 'export')->name('export');
        Route::get('next-code', 'getNextCode')->name('next-code');
        Route::get('salespersons', 'getSalespersons')->name('salespersons');
        Route::get('trashed', 'trashed')->name('trashed');
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{customer}', 'show')->name('show');
        Route::put('{customer}', 'update')->name('update');
        Route::delete('{customer}', 'destroy')->name('destroy');
        Route::patch('{id}/restore', 'restore')->name('restore');
        Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
    });

    // Accounts Controller
    Route::controller(AccountsController::class)->prefix('accounts')->name('accounts.')->group(function () {
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

    Route::prefix('suppliers')->name('suppliers.')->group(function () {

        Route::controller(PurchasesController::class)->prefix('purchases')->name('purchases.')->group(function () {
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{purchase}', 'show')->name('show');
            Route::put('{purchase}', 'update')->name('update');
            Route::delete('{purchase}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
            Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
        });
         
    });

    Route::prefix('setups')->name('setups.')->group(function () {
        
        // Warehouses Controller
        Route::controller(WarehousesController::class)->prefix('warehouses')->name('warehouses.')->group(function () {
            Route::get('trashed', 'trashed')->name('trashed');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('{warehouse}', 'show')->name('show');
            Route::put('{warehouse}', 'update')->name('update');
            Route::delete('{warehouse}', 'destroy')->name('destroy');
            Route::patch('{id}/restore', 'restore')->name('restore');
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
});