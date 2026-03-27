<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable features for this installation.
    | Set a feature to false to hide/disable that functionality.
    |
    */

    // Sales & Customers
    'sale_orders'                => true,
    'customer_payment_orders'    => true,
    'customer_returns'           => true,
    'customer_return_orders'     => true,
    'customer_credit_notes'      => true,

    // Purchases & Suppliers
    'purchase_returns'           => true,
    'supplier_credit_notes'      => true,

    // Expenses
    'expense'                    => true,
    'expense_deferred_payment'   => true,

    // Finance & Accounts
    'multi_currency'             => true,
    'income_transactions'        => true,
    'account_transfers'          => true,
    'account_adjustments'        => true,

    // Inventory
    'item_transfers'             => true,
    'item_adjustments'           => true,
    'price_lists'                => true,
    'item_cost_history'          => true,

    // HR / Employees
    'employee_management'        => true,
    'salary_management'          => true,
    'employee_commissions'       => true,
    'advance_loans'              => true,

    // Reports
    'report_capital'             => true,
    'report_profit'              => true,
    'report_employee_sales'      => true,
    'report_expense_analysis'    => true,
    'report_warehouse'           => true,
    'report_sales_category'      => true,
    'report_customer_aging'      => true,
    'report_item_sales'          => true,

    // System
    'activity_logs'              => true,
    'tax_codes'                  => true,

];
