<?php

namespace Tests\Feature\Customers\PaymentOrders\Concerns;

use App\Models\Accounts\Account;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerPayment;
use App\Models\Employees\Employee;
use App\Models\Setting;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Employees\Department;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;

trait HasCustomerPaymentOrderSetup
{
    protected User $admin;
    protected User $salesman;
    protected Employee $linkedEmployee;
    protected Customer $customer;
    protected Currency $currency;
    protected CustomerPaymentTerm $paymentTerm;
    protected Account $account;

    public function setUpCustomerPaymentOrders(): void
    {
        $this->admin   = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->salesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);

        // Employee record linked to the salesman so RoleHelper::getSalesmanEmployee() resolves
        $salesDept = Department::factory()->create(['name' => 'Sales']);
        $this->linkedEmployee = Employee::factory()->create([
            'user_id'       => $this->salesman->id,
            'department_id' => $salesDept->id,
            'is_active'     => true,
        ]);

        $this->customer = Customer::factory()->create([
            'salesperson_id' => $this->linkedEmployee->id,
            'created_by'     => $this->admin->id,
            'updated_by'     => $this->admin->id,
            'is_active'      => true,
        ]);

        $this->currency     = Currency::factory()->usd()->create(['name' => 'US Dollar', 'calculation_type' => 'multiply']);
        $this->paymentTerm  = CustomerPaymentTerm::factory()->create();
        $this->account      = Account::factory()->create(['name' => 'Cash Account']);

        Setting::create([
            'group_name'  => 'customer_payments',
            'key_name'    => 'code_counter',
            'value'       => '999',
            'data_type'   => 'number',
            'description' => 'Customer payment code counter',
        ]);
    }

    protected function paymentPayload(array $overrides = []): array
    {
        return array_merge([
            'date'                     => '2025-01-15',
            'prefix'                   => 'RCT',
            'customer_id'              => $this->customer->id,
            'customer_payment_term_id' => $this->paymentTerm->id,
            'currency_id'              => $this->currency->id,
            'currency_rate'            => 1.25,
            'amount'                   => 1000.00,
            'amount_usd'               => 1250.00,
            'credit_limit'             => 5000.00,
            'last_payment_amount'      => 500.00,
            'rtc_book_number'          => 'RTC-' . uniqid(),
            'note'                     => 'Test payment order note',
        ], $overrides);
    }

    // Creates a payment order via the HTTP API (defaults to salesman user)
    protected function createOrderViaApi(array $overrides = [], string $as = 'salesman'): CustomerPayment
    {
        $user = $as === 'admin' ? $this->admin : $this->salesman;
        $this->actingAs($user, 'sanctum');

        $this->postJson(route('customers.payment-orders.store'), $this->paymentPayload($overrides))
            ->assertCreated();

        return CustomerPayment::latest()->first();
    }

    // Creates a payment via factory — bypasses HTTP, useful for seeding specific states
    protected function createOrderViaFactory(?string $state = null, array $overrides = []): CustomerPayment
    {
        $base = [
            'customer_id'              => $this->customer->id,
            'currency_id'              => $this->currency->id,
            'customer_payment_term_id' => $this->paymentTerm->id,
        ];

        if ($state === 'approved') {
            $base['approved_by'] = $this->admin->id;
            $base['account_id']  = $this->account->id;
        }

        $factory = CustomerPayment::factory();

        if ($state) {
            $factory = $factory->{$state}();
        }

        return $factory->create(array_merge($base, $overrides));
    }
}
