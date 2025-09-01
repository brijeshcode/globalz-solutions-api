<?php

use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setting;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Accounts\Account;
use App\Models\User;

uses()->group('api', 'setup', 'expenses.transaction', 'expenses');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
    
    // Create expense transaction code counter setting (starting from 100)
    Setting::create([
        'group_name' => 'expense_transactions',
        'key_name' => 'code_counter', 
        'value' => '100',
        'data_type' => 'number',
        'description' => 'Expense transaction code counter starting from 100'
    ]);
    
    // Create related models for testing
    $this->expenseCategory = ExpenseCategory::factory()->create();
    $this->account = Account::factory()->create();
    
    // Helper method for base expense transaction data
    $this->getBaseExpenseTransactionData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'subject' => 'Test Expense',
            'amount' => 1500.00,
        ], $overrides);
    };
});

describe('Expense Transactions API', function () {
    it('can list expense transactions', function () {
        ExpenseTransaction::factory()->count(3)->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('expense-transactions.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'date',
                        'code',
                        'subject',
                        'amount',
                        'expense_category',
                        'account',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create an expense transaction with minimum required fields', function () {
        $data = [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 500.00,
        ];

        $response = $this->postJson(route('expense-transactions.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'date',
                    'code',
                    'amount',
                    'expense_category',
                    'account',
                ]
            ]);

        $this->assertDatabaseHas('expense_transactions', [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 500.00,
        ]);

        // Check if code was auto-generated starting from 100
        $expenseTransaction = ExpenseTransaction::where('amount', 500.00)->first();
        expect((int)$expenseTransaction->code)->toBeGreaterThanOrEqual(100);
    });

    it('can create an expense transaction with all fields', function () {
        $data = [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'subject' => 'Complete Expense Transaction',
            'amount' => 2500.75,
            'order_number' => 'ORD-12345',
            'check_number' => 'CHK-67890',
            'bank_ref_number' => 'BNK-98765',
            'note' => 'Complete expense transaction with all fields',
        ];

        $response = $this->postJson(route('expense-transactions.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'subject' => 'Complete Expense Transaction',
                    'amount' => 2500.75,
                    'order_number' => 'ORD-12345',
                    'check_number' => 'CHK-67890',
                    'bank_ref_number' => 'BNK-98765',
                    'note' => 'Complete expense transaction with all fields',
                ]
            ]);

        // Verify code was auto-generated
        $expenseTransaction = ExpenseTransaction::where('subject', 'Complete Expense Transaction')->first();
        expect($expenseTransaction->code)->not()->toBeNull();
        expect((int)$expenseTransaction->code)->toBeGreaterThanOrEqual(100);

        $this->assertDatabaseHas('expense_transactions', [
            'subject' => 'Complete Expense Transaction',
            'amount' => 2500.75,
            'order_number' => 'ORD-12345',
        ]);
    });

    it('auto-generates expense transaction codes when not provided', function () {
        $data = [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 750.00,
        ];

        $response = $this->postJson(route('expense-transactions.store'), $data);

        $response->assertCreated();
        
        $expenseTransaction = ExpenseTransaction::where('amount', 750.00)->first();
        expect($expenseTransaction->code)->not()->toBeNull();
        expect((int)$expenseTransaction->code)->toBeGreaterThanOrEqual(100);
    });

    it('ignores provided code and always generates new one', function () {
        $data = [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 999.00,
            'code' => '99999',
        ];

        $response = $this->postJson(route('expense-transactions.store'), $data);

        $response->assertCreated();
        
        $expenseTransaction = ExpenseTransaction::where('amount', 999.00)->first();
        expect($expenseTransaction->code)->not()->toBe('99999');
        expect((int)$expenseTransaction->code)->toBeGreaterThanOrEqual(100);
    });

    it('can show an expense transaction', function () {
        $expenseTransaction = ExpenseTransaction::factory()->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('expense-transactions.show', $expenseTransaction));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $expenseTransaction->id,
                    'code' => $expenseTransaction->code,
                    'date' => $expenseTransaction->date->format('Y-m-d'),
                    'amount' => $expenseTransaction->amount,
                ]
            ]);
    });

    it('can update an expense transaction', function () {
        $expenseTransaction = ExpenseTransaction::factory()->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);
        
        $originalCode = $expenseTransaction->code;
        
        $data = [
            'date' => '2025-09-01',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'subject' => 'Updated Expense',
            'amount' => 3500.00,
            'note' => 'Updated expense transaction',
        ];

        $response = $this->putJson(route('expense-transactions.update', $expenseTransaction), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'code' => $originalCode,
                    'subject' => 'Updated Expense',
                    'amount' => 3500.00,
                ]
            ]);

        $this->assertDatabaseHas('expense_transactions', [
            'id' => $expenseTransaction->id,
            'code' => $originalCode,
            'subject' => 'Updated Expense',
            'amount' => 3500.00,
        ]);
    });

    it('code cannot be updated once set', function () {
        $expenseTransaction = ExpenseTransaction::factory()->create();
        $originalCode = $expenseTransaction->code;
        
        $data = [
            'date' => '2025-09-01',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
            'code' => '99999',
        ];

        $response = $this->putJson(route('expense-transactions.update', $expenseTransaction), $data);

        $response->assertOk();
        
        $updatedExpenseTransaction = $expenseTransaction->fresh();
        expect($updatedExpenseTransaction->code)->toBe($originalCode);
        expect($updatedExpenseTransaction->code)->not()->toBe('99999');
    });

    it('can delete an expense transaction', function () {
        $expenseTransaction = ExpenseTransaction::factory()->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->deleteJson(route('expense-transactions.destroy', $expenseTransaction));

        $response->assertStatus(204);
        $this->assertSoftDeleted('expense_transactions', ['id' => $expenseTransaction->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('expense-transactions.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'expense_category_id', 'account_id', 'amount']);
    });

    it('validates foreign key references', function () {
        $response = $this->postJson(route('expense-transactions.store'), [
            'date' => '2025-08-31',
            'expense_category_id' => 99999,
            'account_id' => 99999,
            'amount' => 1000.00,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'expense_category_id',
                'account_id'
            ]);
    });

    it('validates amount is numeric and positive', function () {
        $response = $this->postJson(route('expense-transactions.store'), [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => -500.00,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    });

    it('validates date format', function () {
        $response = $this->postJson(route('expense-transactions.store'), [
            'date' => 'invalid-date',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    });

    it('can search expense transactions by subject', function () {
        ExpenseTransaction::factory()->create([
            'subject' => 'Searchable Expense',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);
        ExpenseTransaction::factory()->create([
            'subject' => 'Another Expense',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('expense-transactions.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['subject'])->toBe('Searchable Expense');
    });

    it('can search expense transactions by code', function () {
        $expenseTransaction1 = ExpenseTransaction::factory()->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);
        $expenseTransaction2 = ExpenseTransaction::factory()->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('expense-transactions.index', ['search' => $expenseTransaction1->code]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe($expenseTransaction1->code);
    });

    it('can filter by expense category', function () {
        $category1 = ExpenseCategory::factory()->create(['name' => 'Office Supplies']);
        $category2 = ExpenseCategory::factory()->create(['name' => 'Travel']);
        
        ExpenseTransaction::factory()->create([
            'expense_category_id' => $category1->id,
            'account_id' => $this->account->id,
        ]);
        ExpenseTransaction::factory()->create([
            'expense_category_id' => $category2->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('expense-transactions.index', ['expense_category_id' => $category1->id]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['expense_category']['id'])->toBe($category1->id);
    });

    it('can filter by account', function () {
        $account1 = Account::factory()->create(['name' => 'Cash Account']);
        $account2 = Account::factory()->create(['name' => 'Bank Account']);
        
        ExpenseTransaction::factory()->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $account1->id,
        ]);
        ExpenseTransaction::factory()->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $account2->id,
        ]);

        $response = $this->getJson(route('expense-transactions.index', ['account_id' => $account1->id]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['account']['id'])->toBe($account1->id);
    });

    it('can filter by date', function () {
        ExpenseTransaction::factory()->create([
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);
        ExpenseTransaction::factory()->create([
            'date' => '2025-09-01',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('expense-transactions.index', ['date' => '2025-08-31']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['date'])->toBe('2025-08-31');
    });

    it('can filter by date range', function () {
        ExpenseTransaction::factory()->create([
            'date' => '2025-08-15',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);
        ExpenseTransaction::factory()->create([
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);
        ExpenseTransaction::factory()->create([
            'date' => '2025-09-15',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('expense-transactions.index', [
            'start_date' => '2025-08-01',
            'end_date' => '2025-08-31'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(2);
    });

    it('can sort expense transactions by date', function () {
        ExpenseTransaction::factory()->create([
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);
        ExpenseTransaction::factory()->create([
            'date' => '2025-08-15',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('expense-transactions.index', [
            'sort_by' => 'date',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['date'])->toBe('2025-08-15');
        expect($data[1]['date'])->toBe('2025-08-31');
    });

    it('can list trashed expense transactions', function () {
        $expenseTransaction = ExpenseTransaction::factory()->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);
        $expenseTransaction->delete();

        $response = $this->getJson(route('expense-transactions.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'date',
                        'code',
                        'subject',
                        'amount',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed expense transaction', function () {
        $expenseTransaction = ExpenseTransaction::factory()->create();
        $expenseTransaction->delete();

        $response = $this->patchJson(route('expense-transactions.restore', $expenseTransaction->id));

        $response->assertOk();
        $this->assertDatabaseHas('expense_transactions', [
            'id' => $expenseTransaction->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed expense transaction', function () {
        $expenseTransaction = ExpenseTransaction::factory()->create();
        $expenseTransaction->delete();

        $response = $this->deleteJson(route('expense-transactions.force-delete', $expenseTransaction->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('expense_transactions', ['id' => $expenseTransaction->id]);
    });

    it('validates maximum length for string fields', function () {
        $response = $this->postJson(route('expense-transactions.store'), [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
            'subject' => str_repeat('a', 201),
            'order_number' => str_repeat('b', 101),
            'check_number' => str_repeat('c', 101),
            'bank_ref_number' => str_repeat('d', 101),
            'note' => str_repeat('e', 251),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['subject', 'order_number', 'check_number', 'bank_ref_number', 'note']);
    });

    it('validates amount range', function () {
        $response = $this->postJson(route('expense-transactions.store'), [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 9999999999999.99,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    });

    it('can paginate expense transactions', function () {
        ExpenseTransaction::factory()->count(7)->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('expense-transactions.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');
        
        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('sets created_by and updated_by fields automatically', function () {
        $expenseTransaction = ExpenseTransaction::factory()->create([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
        ]);

        expect($expenseTransaction->created_by)->toBe($this->user->id);
        expect($expenseTransaction->updated_by)->toBe($this->user->id);

        $expenseTransaction->update(['subject' => 'Updated Subject']);
        expect($expenseTransaction->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent expense transaction', function () {
        $response = $this->getJson(route('expense-transactions.show', 999));

        $response->assertNotFound();
    });
});

describe('Expense Transaction Code Generation Tests', function () {
    it('gets next code when current setting is 101', function () {
        Setting::set('expense_transactions', 'code_counter', 101, 'number');
        
        $nextCode = ExpenseTransaction::getNextSuggestedCode();
        
        expect((int) $nextCode)->toBe(101);
    });

    it('creates expense transaction with current counter and increments correctly', function () {
        Setting::set('expense_transactions', 'code_counter', 105, 'number');
        
        $response = $this->postJson(route('expense-transactions.store'), [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
        ]);
        
        $response->assertCreated();
        $code = (int) $response->json('data.code');
        expect($code)->toBe(105);
        
        $nextCode = ExpenseTransaction::getNextSuggestedCode();
        expect((int) $nextCode)->toBe(106);
    });

    it('auto-creates code counter setting when missing', function () {
        Setting::where('group_name', 'expense_transactions')
            ->where('key_name', 'code_counter')
            ->delete();
        
        Setting::clearCache();
        
        $existingSetting = Setting::where('group_name', 'expense_transactions')
            ->where('key_name', 'code_counter')
            ->first();
        expect($existingSetting)->toBeNull();
        
        $response = $this->postJson(route('expense-transactions.store'), [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
        ]);
        
        $response->assertCreated();
        $firstCode = (int) $response->json('data.code');
        expect($firstCode)->toBe(100);
        
        $setting = Setting::where('group_name', 'expense_transactions')
            ->where('key_name', 'code_counter')
            ->first();
        expect($setting)->not()->toBeNull();
        expect($setting->data_type)->toBe('number');
        expect((int) $setting->value)->toBe(101);
    });

    it('generates sequential expense transaction codes', function () {
        ExpenseTransaction::withTrashed()->forceDelete();
        
        $response1 = $this->postJson(route('expense-transactions.store'), [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
        ]);
        $response1->assertCreated();
        $code1 = (int) $response1->json('data.code');

        $response2 = $this->postJson(route('expense-transactions.store'), [
            'date' => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id' => $this->account->id,
            'amount' => 2000.00,
        ]);
        $response2->assertCreated();
        $code2 = (int) $response2->json('data.code');

        expect($code1)->toBeGreaterThanOrEqual(100);
        expect($code2)->toBe($code1 + 1);
    });

    it('handles concurrent expense transaction creation with code generation', function () {
        $expenseTransactions = [];
        for ($i = 0; $i < 5; $i++) {
            $expenseTransactions[] = ExpenseTransaction::factory()->create([
                'expense_category_id' => $this->expenseCategory->id,
                'account_id' => $this->account->id,
            ]);
        }

        $codes = collect($expenseTransactions)->map(fn($et) => (int) $et->code)->sort()->values();
        
        for ($i = 1; $i < count($codes); $i++) {
            expect($codes[$i])->toBeGreaterThan($codes[$i - 1]);
        }
    });
});
