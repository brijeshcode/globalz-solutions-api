<?php

use App\Models\Accounts\IncomeTransaction;
use App\Models\Setting;
use App\Models\Setups\Accounts\IncomeCategory;
use App\Models\Accounts\Account;
use App\Models\User;

uses()->group('api', 'setup', 'accounts.income-transaction', 'accounts');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    // Create income transaction code counter setting (starting from 100)
    Setting::create([
        'group_name' => 'income_transactions',
        'key_name' => 'code_counter',
        'value' => '100',
        'data_type' => 'number',
        'description' => 'Income transaction code counter starting from 100'
    ]);

    // Create related models for testing
    $this->incomeCategory = IncomeCategory::factory()->create();
    $this->account = Account::factory()->create();

    // Helper method for base income transaction data
    $this->getBaseIncomeTransactionData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'subject' => 'Test Income',
            'amount' => 1500.00,
        ], $overrides);
    };
});

describe('Income Transactions API', function () {
    it('can list income transactions', function () {
        IncomeTransaction::factory()->count(3)->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('income-transactions.index'));

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
                        'income_category',
                        'account',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create an income transaction with minimum required fields', function () {
        $data = [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => 500.00,
        ];

        $response = $this->postJson(route('income-transactions.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'date',
                    'code',
                    'amount',
                    'income_category',
                    'account',
                ]
            ]);

        $this->assertDatabaseHas('income_transactions', [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => "500.00",
        ]);

        // Check if code was auto-generated starting from 100
        $incomeTransaction = IncomeTransaction::where('amount', 500.00)->first();
        expect((int)$incomeTransaction->code)->toBeGreaterThanOrEqual(100);
    });

    it('can create an income transaction with all fields', function () {
        $data = [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'subject' => 'Complete Income Transaction',
            'amount' => 2500.75,
            'order_number' => 'ORD-12345',
            'check_number' => 'CHK-67890',
            'bank_ref_number' => 'BNK-98765',
            'note' => 'Complete income transaction with all fields',
        ];

        $response = $this->postJson(route('income-transactions.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'subject' => 'Complete Income Transaction',
                    'amount' => 2500.75,
                    'order_number' => 'ORD-12345',
                    'check_number' => 'CHK-67890',
                    'bank_ref_number' => 'BNK-98765',
                    'note' => 'Complete income transaction with all fields',
                ]
            ]);

        // Verify code was auto-generated
        $incomeTransaction = IncomeTransaction::where('subject', 'Complete Income Transaction')->first();
        expect($incomeTransaction->code)->not()->toBeNull();
        expect((int)$incomeTransaction->code)->toBeGreaterThanOrEqual(100);

        $this->assertDatabaseHas('income_transactions', [
            'subject' => 'Complete Income Transaction',
            'amount' => 2500.75,
            'order_number' => 'ORD-12345',
        ]);
    });

    it('auto-generates income transaction codes when not provided', function () {
        $data = [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => 750.00,
        ];

        $response = $this->postJson(route('income-transactions.store'), $data);

        $response->assertCreated();

        $incomeTransaction = IncomeTransaction::where('amount', 750.00)->first();
        expect($incomeTransaction->code)->not()->toBeNull();
        expect((int)$incomeTransaction->code)->toBeGreaterThanOrEqual(100);
    });

    it('ignores provided code and always generates new one', function () {
        $data = [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => 999.00,
            'code' => '99999',
        ];

        $response = $this->postJson(route('income-transactions.store'), $data);

        $response->assertCreated();

        $incomeTransaction = IncomeTransaction::where('amount', 999.00)->first();
        expect($incomeTransaction->code)->not()->toBe('99999');
        expect((int)$incomeTransaction->code)->toBeGreaterThanOrEqual(100);
    });

    it('can show an income transaction', function () {
        $incomeTransaction = IncomeTransaction::factory()->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('income-transactions.show', $incomeTransaction));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $incomeTransaction->id,
                    'code' => $incomeTransaction->code,
                    'date' => $incomeTransaction->date->format('Y-m-d'),
                    'amount' => $incomeTransaction->amount,
                ]
            ]);
    });

    it('can update an income transaction', function () {
        $incomeTransaction = IncomeTransaction::factory()->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        $originalCode = $incomeTransaction->code;

        $data = [
            'date' => '2025-09-01',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'subject' => 'Updated Income',
            'amount' => 3500.00,
            'note' => 'Updated income transaction',
        ];

        $response = $this->putJson(route('income-transactions.update', $incomeTransaction), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'code' => $originalCode,
                    'subject' => 'Updated Income',
                    'amount' => 3500.00,
                ]
            ]);

        $this->assertDatabaseHas('income_transactions', [
            'id' => $incomeTransaction->id,
            'code' => $originalCode,
            'subject' => 'Updated Income',
            'amount' => 3500.00,
        ]);
    });

    it('code cannot be updated once set', function () {
        $incomeTransaction = IncomeTransaction::factory()->create();
        $originalCode = $incomeTransaction->code;

        $data = [
            'date' => '2025-09-01',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
            'code' => '99999',
        ];

        $response = $this->putJson(route('income-transactions.update', $incomeTransaction), $data);

        $response->assertOk();

        $updatedIncomeTransaction = $incomeTransaction->fresh();
        expect($updatedIncomeTransaction->code)->toBe($originalCode);
        expect($updatedIncomeTransaction->code)->not()->toBe('99999');
    });

    it('can delete an income transaction', function () {
        $incomeTransaction = IncomeTransaction::factory()->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->deleteJson(route('income-transactions.destroy', $incomeTransaction));

        $response->assertStatus(204);
        $this->assertSoftDeleted('income_transactions', ['id' => $incomeTransaction->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('income-transactions.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'income_category_id', 'account_id', 'amount']);
    });

    it('validates foreign key references', function () {
        $response = $this->postJson(route('income-transactions.store'), [
            'date' => '2025-08-31',
            'income_category_id' => 99999,
            'account_id' => 99999,
            'amount' => 1000.00,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'income_category_id',
                'account_id'
            ]);
    });

    it('validates amount is numeric and positive', function () {
        $response = $this->postJson(route('income-transactions.store'), [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => -500.00,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    });

    it('validates date format', function () {
        $response = $this->postJson(route('income-transactions.store'), [
            'date' => 'invalid-date',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    });

    it('can search income transactions by subject', function () {
        IncomeTransaction::factory()->create([
            'subject' => 'Searchable Income',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);
        IncomeTransaction::factory()->create([
            'subject' => 'Another Income',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('income-transactions.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['subject'])->toBe('Searchable Income');
    });

    it('can search income transactions by code', function () {
        $incomeTransaction1 = IncomeTransaction::factory()->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);
        $incomeTransaction2 = IncomeTransaction::factory()->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('income-transactions.index', ['search' => $incomeTransaction1->code]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe($incomeTransaction1->code);
    });

    it('can filter by income category', function () {
        $category1 = IncomeCategory::factory()->create(['name' => 'Sales Revenue']);
        $category2 = IncomeCategory::factory()->create(['name' => 'Service Income']);

        IncomeTransaction::factory()->create([
            'income_category_id' => $category1->id,
            'account_id' => $this->account->id,
        ]);
        IncomeTransaction::factory()->create([
            'income_category_id' => $category2->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('income-transactions.index', ['income_category_id' => $category1->id]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['income_category']['id'])->toBe($category1->id);
    });

    it('can filter by account', function () {
        $account1 = Account::factory()->create(['name' => 'Cash Account']);
        $account2 = Account::factory()->create(['name' => 'Bank Account']);

        IncomeTransaction::factory()->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $account1->id,
        ]);
        IncomeTransaction::factory()->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $account2->id,
        ]);

        $response = $this->getJson(route('income-transactions.index', ['account_id' => $account1->id]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['account']['id'])->toBe($account1->id);
    });

    it('can filter by date', function () {
        IncomeTransaction::factory()->create([
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);
        IncomeTransaction::factory()->create([
            'date' => '2025-09-01',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('income-transactions.index', ['date' => '2025-08-31']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['date'])->toBe('2025-08-31');
    });

    it('can filter by date range', function () {
        IncomeTransaction::factory()->create([
            'date' => '2025-08-15',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);
        IncomeTransaction::factory()->create([
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);
        IncomeTransaction::factory()->create([
            'date' => '2025-09-15',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('income-transactions.index', [
            'start_date' => '2025-08-01',
            'end_date' => '2025-08-31'
        ]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(2);
    });

    it('can sort income transactions by date', function () {
        IncomeTransaction::factory()->create([
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);
        IncomeTransaction::factory()->create([
            'date' => '2025-08-15',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('income-transactions.index', [
            'sort_by' => 'date',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data[0]['date'])->toBe('2025-08-15');
        expect($data[1]['date'])->toBe('2025-08-31');
    });

    it('can list trashed income transactions', function () {
        $incomeTransaction = IncomeTransaction::factory()->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);
        $incomeTransaction->delete();

        $response = $this->getJson(route('income-transactions.trashed'));

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

    it('can restore a trashed income transaction', function () {
        $incomeTransaction = IncomeTransaction::factory()->create();
        $incomeTransaction->delete();

        $response = $this->patchJson(route('income-transactions.restore', $incomeTransaction->id));

        $response->assertOk();
        $this->assertDatabaseHas('income_transactions', [
            'id' => $incomeTransaction->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed income transaction', function () {
        $incomeTransaction = IncomeTransaction::factory()->create();
        $incomeTransaction->delete();

        $response = $this->deleteJson(route('income-transactions.force-delete', $incomeTransaction->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('income_transactions', ['id' => $incomeTransaction->id]);
    });

    it('validates maximum length for string fields', function () {
        $response = $this->postJson(route('income-transactions.store'), [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
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
        $response = $this->postJson(route('income-transactions.store'), [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => 9999999999999.99,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    });

    it('can paginate income transactions', function () {
        IncomeTransaction::factory()->count(7)->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('income-transactions.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('sets created_by and updated_by fields automatically', function () {
        $incomeTransaction = IncomeTransaction::factory()->create([
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
        ]);

        expect($incomeTransaction->created_by)->toBe($this->user->id);
        expect($incomeTransaction->updated_by)->toBe($this->user->id);

        $incomeTransaction->update(['subject' => 'Updated Subject']);
        expect($incomeTransaction->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent income transaction', function () {
        $response = $this->getJson(route('income-transactions.show', 999));

        $response->assertNotFound();
    });
});

describe('Income Transaction Code Generation Tests', function () {
    it('gets next code when current setting is 101', function () {
        Setting::set('income_transactions', 'code_counter', 101, 'number');

        $nextCode = IncomeTransaction::getNextSuggestedCode();

        expect((int) $nextCode)->toBe(101);
    });

    it('creates income transaction with current counter and increments correctly', function () {
        Setting::set('income_transactions', 'code_counter', 105, 'number');

        $response = $this->postJson(route('income-transactions.store'), [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
        ]);

        $response->assertCreated();
        $code = (int) $response->json('data.code');
        expect($code)->toBe(105);

        $nextCode = IncomeTransaction::getNextSuggestedCode();
        expect((int) $nextCode)->toBe(106);
    });

    it('auto-creates code counter setting when missing', function () {
        Setting::where('group_name', 'income_transactions')
            ->where('key_name', 'code_counter')
            ->delete();

        Setting::clearCache();

        $existingSetting = Setting::where('group_name', 'income_transactions')
            ->where('key_name', 'code_counter')
            ->first();
        expect($existingSetting)->toBeNull();

        $response = $this->postJson(route('income-transactions.store'), [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
        ]);

        $response->assertCreated();
        $firstCode = (int) $response->json('data.code');
        expect($firstCode)->toBe(100);

        $setting = Setting::where('group_name', 'income_transactions')
            ->where('key_name', 'code_counter')
            ->first();
        expect($setting)->not()->toBeNull();
        expect($setting->data_type)->toBe('number');
        expect((int) $setting->value)->toBe(101);
    });

    it('generates sequential income transaction codes', function () {
        IncomeTransaction::withTrashed()->forceDelete();

        $response1 = $this->postJson(route('income-transactions.store'), [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => 1000.00,
        ]);
        $response1->assertCreated();
        $code1 = (int) $response1->json('data.code');

        $response2 = $this->postJson(route('income-transactions.store'), [
            'date' => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id' => $this->account->id,
            'amount' => 2000.00,
        ]);
        $response2->assertCreated();
        $code2 = (int) $response2->json('data.code');

        expect($code1)->toBeGreaterThanOrEqual(100);
        expect($code2)->toBe($code1 + 1);
    });

    it('handles concurrent income transaction creation with code generation', function () {
        $incomeTransactions = [];
        for ($i = 0; $i < 5; $i++) {
            $incomeTransactions[] = IncomeTransaction::factory()->create([
                'income_category_id' => $this->incomeCategory->id,
                'account_id' => $this->account->id,
            ]);
        }

        $codes = collect($incomeTransactions)->map(fn($it) => (int) $it->code)->sort()->values();

        for ($i = 1; $i < count($codes); $i++) {
            expect($codes[$i])->toBeGreaterThan($codes[$i - 1]);
        }
    });
});
