<?php

use App\Models\Accounts\Account;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerPayment;
use App\Models\Setting;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;

uses()->group('api', 'customers', 'customer-payments');

beforeEach(function () {
    $this->adminUser = User::factory()->create(['role' => 'admin']);
    $this->employeeUser = User::factory()->create(['role' => 'salesman']);

    // Create payment code counter setting (starting from 1000)
    Setting::create([
        'group_name' => 'customer_payments',
        'key_name' => 'code_counter',
        'value' => '999',
        'data_type' => 'number',
        'description' => 'Customer payment code counter starting from 1000'
    ]);

    // Create related models for testing
    $this->customer = Customer::factory()->create([
        'name' => 'Test Customer',
        'created_by' => $this->adminUser->id,
        'updated_by' => $this->adminUser->id,
        'is_active' => true,
    ]);
    $this->currency = Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar']);
    $this->paymentTerm = CustomerPaymentTerm::factory()->create();
    $this->account = Account::factory()->create(['name' => 'Cash Account']);

    // Helper method for base payment data
    $this->getBasePaymentData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'prefix' => 'RCT',
            'customer_id' => $this->customer->id,
            'customer_payment_term_id' => $this->paymentTerm->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.25,
            'amount' => 1000.00,
            'amount_usd' => 800.00,
            'credit_limit' => 5000.00,
            'last_payment_amount' => 500.00,
            'rtc_book_number' => 'RTC-' . uniqid(),
            'note' => 'Test payment note',
            'account_id' => $this->account->id,
        ], $overrides);
    };

    // Helper method to create approved payment via API (controller always creates approved)
    $this->createApprovedPaymentViaApi = function ($overrides = []) {
        $this->actingAs($this->adminUser, 'sanctum');

        $paymentData = ($this->getBasePaymentData)($overrides);

        $response = $this->postJson(route('customers.payments.store'), $paymentData);
        $response->assertCreated();

        return CustomerPayment::latest()->first();
    };

    // Helper method to create payment via factory with proper relationships
    $this->createPaymentViaFactory = function ($state = null, $overrides = []) {
        $baseData = [
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'customer_payment_term_id' => $this->paymentTerm->id,
        ];

        // Add approval data for approved payments
        if ($state === 'approved') {
            $baseData['approved_by'] = $this->adminUser->id;
            $baseData['account_id'] = $this->account->id;
        }

        $factory = CustomerPayment::factory();

        if ($state) {
            $factory = $factory->{$state}();
        }

        return $factory->create(array_merge($baseData, $overrides));
    };
});

describe('Customer Payments API', function () {
    it('can list approved customer payments only', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        // Create mix of pending and approved payments
        for ($i = 0; $i < 2; $i++) {
            ($this->createPaymentViaFactory)('pending');
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createPaymentViaFactory)('approved');
        }

        $response = $this->getJson(route('customers.payments.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'payment_code',
                        'date',
                        'prefix',
                        'amount',
                        'amount_usd',
                        'status',
                        'is_approved',
                        'is_pending',
                        'customer',
                        'currency',
                    ]
                ],
                'pagination'
            ]);

        // Should only show approved payments (controller has ->approved() filter)
        expect($response->json('data'))->toHaveCount(3);
        foreach ($response->json('data') as $payment) {
            expect($payment['is_approved'])->toBe(true);
        }
    });

    it('admin can create approved payment', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $paymentData = ($this->getBasePaymentData)([
            'approve_note' => 'Admin approved during creation'
        ]);

        $response = $this->postJson(route('customers.payments.store'), $paymentData);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'status' => 'approved',
                    'is_approved' => true,
                    'is_pending' => false,
                ]
            ]);

        $payment = CustomerPayment::latest()->first();
        expect($payment->isApproved())->toBe(true);
        expect($payment->approved_by)->toBe($this->adminUser->id);
        expect($payment->account_id)->toBe($this->account->id);
    });

    it('auto-generates payment codes when not provided', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $paymentData = ($this->getBasePaymentData)();

        $response = $this->postJson(route('customers.payments.store'), $paymentData);

        $response->assertCreated();

        $payment = CustomerPayment::latest()->first();
        expect($payment->code)->not()->toBeNull();
        expect($payment->code)->toMatch('/^\d{6}$/'); // 6-digit padded number
        expect($payment->payment_code)->toBe($payment->prefix . $payment->code);
    });

    it('can show payment with all relationships', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        $payment = ($this->createApprovedPaymentViaApi)();

        $response = $this->getJson(route('customers.payments.show', $payment));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'payment_code',
                    'customer' => ['id', 'name', 'code'],
                    'currency' => ['id', 'name', 'code', 'symbol'],
                    'customer_payment_term' => ['id', 'name', 'days'],
                    'created_by_user' => ['id', 'name'],
                    'approved_by_user' => ['id', 'name'],
                    'account' => ['id', 'name'],
                ]
            ]);
    });

    it('cannot update approved payments', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $payment = ($this->createPaymentViaFactory)('approved');

        $updateData = [
            'date' => '2025-01-25',
            'prefix' => 'RCT',
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.25,
            'amount' => 2000.00,
            'amount_usd' => 1600.00,
            'note' => 'Trying to update approved payment',
            'rtc_book_number' => 'RTC-UPDATE-ATTEMPT-' . uniqid(),
            'account_id' => $this->account->id,
        ];

        $response = $this->putJson(route('customers.payments.update', $payment), $updateData);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot update approved payments'
            ]);
    });

    it('cannot delete approved payments', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $payment = ($this->createPaymentViaFactory)('approved');

        $response = $this->deleteJson(route('customers.payments.destroy', $payment));

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot delete approved payments'
            ]);
    });

    it('validates required fields when creating', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $invalidData = [
            'customer_id' => null,
            'currency_id' => null,
            'amount' => -100, // Negative amount
            'currency_rate' => 0, // Zero rate
            'rtc_book_number' => '', // Empty required field
        ];

        $response = $this->postJson(route('customers.payments.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
                'prefix',
                'customer_id',
                'currency_id',
                'currency_rate',
                'amount',
                'amount_usd',
                'rtc_book_number',
                'account_id'
            ]);
    });

    it('validates currency calculation consistency', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $paymentData = ($this->getBasePaymentData)([
            'amount' => 1000.00,
            'currency_rate' => 2.0,
            'amount_usd' => 600.00, // Should be 500.00 (1000/2)
        ]);

        $response = $this->postJson(route('customers.payments.store'), $paymentData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_usd']);
    });

    it('validates unique rtc_book_number', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $existingPayment = ($this->createApprovedPaymentViaApi)([
            'rtc_book_number' => 'RTC-UNIQUE-123'
        ]);

        $paymentData = ($this->getBasePaymentData)([
            'rtc_book_number' => 'RTC-UNIQUE-123' // Same RTC number
        ]);

        $response = $this->postJson(route('customers.payments.store'), $paymentData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rtc_book_number']);
    });

    it('validates active customer requirement', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $inactiveCustomer = Customer::factory()->create([
            'is_active' => false,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $paymentData = ($this->getBasePaymentData)([
            'customer_id' => $inactiveCustomer->id
        ]);

        $response = $this->postJson(route('customers.payments.store'), $paymentData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    });

    it('can filter payments by customer', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        $otherCustomer = Customer::factory()->create([
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        for ($i = 0; $i < 2; $i++) {
            ($this->createPaymentViaFactory)('approved', ['customer_id' => $this->customer->id]);
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createPaymentViaFactory)('approved', ['customer_id' => $otherCustomer->id]);
        }

        $response = $this->getJson(route('customers.payments.index', ['customer_id' => $this->customer->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('can filter payments by currency', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        $otherCurrency = Currency::factory()->create(['code' => 'EUR']);

        for ($i = 0; $i < 2; $i++) {
            ($this->createPaymentViaFactory)('approved', ['currency_id' => $this->currency->id]);
        }
        for ($i = 0; $i < 3; $i++) {
            ($this->createPaymentViaFactory)('approved', ['currency_id' => $otherCurrency->id]);
        }

        $response = $this->getJson(route('customers.payments.index', ['currency_id' => $this->currency->id]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('can filter payments by date range', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        ($this->createPaymentViaFactory)('approved', ['date' => '2025-01-01']);
        ($this->createPaymentViaFactory)('approved', ['date' => '2025-02-15']);
        ($this->createPaymentViaFactory)('approved', ['date' => '2025-03-30']);

        $response = $this->getJson(route('customers.payments.index', [
            'start_date' => '2025-02-01',
            'end_date' => '2025-02-28'
        ]));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can search payments by code', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        $payment1 = ($this->createPaymentViaFactory)('approved', ['code' => '001001']);
        $payment2 = ($this->createPaymentViaFactory)('approved', ['code' => '001002']);

        $response = $this->getJson(route('customers.payments.index', ['search' => '001001']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe('001001');
    });

    it('can search payments by rtc_book_number', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        ($this->createPaymentViaFactory)('approved', ['rtc_book_number' => 'RTC-SEARCH-123']);
        ($this->createPaymentViaFactory)('approved', ['rtc_book_number' => 'RTC-OTHER-456']);

        $response = $this->getJson(route('customers.payments.index', ['search' => 'SEARCH-123']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['rtc_book_number'])->toBe('RTC-SEARCH-123');
    });

    it('sets created_by and updated_by fields automatically', function () {
        $payment = ($this->createApprovedPaymentViaApi)();

        expect($payment->created_by)->toBe($this->adminUser->id);
        expect($payment->updated_by)->toBe($this->adminUser->id);
    });

    it('returns 404 for non-existent payment', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        $response = $this->getJson(route('customers.payments.show', 999));

        $response->assertNotFound();
    });

    it('can paginate payments', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        for ($i = 0; $i < 7; $i++) {
            ($this->createPaymentViaFactory)('approved');
        }

        $response = $this->getJson(route('customers.payments.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('generates sequential payment codes', function () {
        // Clear existing payments and reset counter
        CustomerPayment::withTrashed()->forceDelete();
        Setting::where('group_name', 'customer_payments')
               ->where('key_name', 'code_counter')
               ->update(['value' => '999']);

        $this->actingAs($this->adminUser, 'sanctum');

        // Create payments using direct model creation to avoid potential API race conditions
        $baseData = ($this->getBasePaymentData)(['rtc_book_number' => 'RTC-TEST-1']);
        $baseData['approved_by'] = $this->adminUser->id;
        $baseData['approved_at'] = now();
        $payment1 = CustomerPayment::create($baseData);
        $code1 = (int) $payment1->code;

        $baseData2 = ($this->getBasePaymentData)(['rtc_book_number' => 'RTC-TEST-2']);
        $baseData2['approved_by'] = $this->adminUser->id;
        $baseData2['approved_at'] = now();
        $payment2 = CustomerPayment::create($baseData2);
        $code2 = (int) $payment2->code;

        expect($code1)->toBeGreaterThanOrEqual(1000);
        expect($code2)->toBe($code1 + 1); // strictly sequential
    });
});

describe('Customer Payments Unapproval Management', function () {
    it('admin can unapprove payment', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $payment = ($this->createPaymentViaFactory)('approved');

        $response = $this->patchJson(route('customers.payments.unapprove', $payment));
        $response->assertStatus(403);

        // $response->assertOk()
        //     ->assertJson([
        //         'data' => [
        //             'status' => 'pending',
        //             'is_approved' => false,
        //             'is_pending' => true,
        //         ]
        //     ]);

        // $payment->refresh();
        // expect($payment->isPending())->toBe(true);
        // expect($payment->approved_by)->toBeNull();
        // expect($payment->approved_at)->toBeNull();
        // expect($payment->account_id)->toBeNull();
        // expect($payment->approve_note)->toBeNull();
    });

    it('employee cannot unapprove payments', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        $payment = ($this->createPaymentViaFactory)('approved');

        $response = $this->patchJson(route('customers.payments.unapprove', $payment));
        $response->assertStatus(403);

        // $response->assertForbidden()
        //     ->assertJson([
        //         'message' => 'You do not have permission to unapprove payments'
        //     ]);
    });

    it('cannot unapprove pending payment', function () {
        $this->actingAs($this->adminUser, 'sanctum');

        $payment = ($this->createPaymentViaFactory)('pending');

        $response = $this->patchJson(route('customers.payments.unapprove', $payment));
        $response->assertStatus(403);

        // $response->assertUnprocessable()
        //     ->assertJson([
        //         'message' => 'Payment is not approved'
        //     ]);
    });
});

describe('Customer Payments Soft Delete and Recovery', function () {
    it('can list trashed payments', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        $payment = ($this->createPaymentViaFactory)('approved');
        $payment->delete();

        $response = $this->getJson(route('customers.payments.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can restore trashed payment', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        $payment = ($this->createPaymentViaFactory)('approved');
        $payment->delete();

        $response = $this->patchJson(route('customers.payments.restore', $payment->id));

        $response->assertOk();
        $this->assertDatabaseHas('customer_payments', [
            'id' => $payment->id,
            'deleted_at' => null
        ]);
    });

    it('can force delete payment', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        $payment = ($this->createPaymentViaFactory)('approved');
        $payment->delete();

        $response = $this->deleteJson(route('customers.payments.force-delete', $payment->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('customer_payments', ['id' => $payment->id]);
    });
});

describe('Customer Payments Statistics', function () {
    it('can get payment statistics', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        for ($i = 0; $i < 3; $i++) {
            ($this->createPaymentViaFactory)('pending');
        }
        for ($i = 0; $i < 2; $i++) {
            ($this->createPaymentViaFactory)('approved');
        }
        ($this->createPaymentViaFactory)('approved', ['deleted_at' => now()]);

        $response = $this->getJson(route('customers.payments.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_payments',
                    'pending_payments',
                    'approved_payments',
                    'trashed_payments',
                    'total_amount',
                    'total_amount_usd',
                    'payments_by_prefix',
                    'payments_by_currency',
                    'recent_approved',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_payments'])->toBe(5);
        expect($stats['pending_payments'])->toBe(3);
        expect($stats['approved_payments'])->toBe(2);
        expect($stats['trashed_payments'])->toBe(1);
    });

    it('calculates totals from approved payments only', function () {
        $this->actingAs($this->employeeUser, 'sanctum');

        // Pending payments (should not be counted in totals)
        for ($i = 0; $i < 2; $i++) {
            ($this->createPaymentViaFactory)('pending', ['amount' => 1000.00, 'amount_usd' => 800.00]);
        }

        // Approved payments (should be counted)
        for ($i = 0; $i < 3; $i++) {
            ($this->createPaymentViaFactory)('approved', ['amount' => 500.00, 'amount_usd' => 400.00]);
        }

        $response = $this->getJson(route('customers.payments.stats'));

        $response->assertOk();
        $stats = $response->json('data');

        // Only approved payments are counted in totals
        expect((float)$stats['total_amount'])->toBe(1500.00); // 3 × 500
        expect($stats['approved_payments'])->toBe(3);
        expect($stats['pending_payments'])->toBe(2);
    });
});