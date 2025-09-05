<?php

use App\Models\Accounts\Account;
use App\Models\Setups\Accounts\AccountType;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;

uses()->group('api', 'setup', 'setup.accounts', 'accounts');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
    
    // Create related models for testing
    $this->accountType = AccountType::factory()->create();
    $this->currency = Currency::factory()->create();
    
    // Helper method for base account data
    $this->getBaseAccountData = function ($overrides = []) {
        return array_merge([
            'name' => 'Test Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
            'description' => 'Test account description',
            'is_active' => true,
        ], $overrides);
    };
});

describe('Accounts API', function () {
    it('can list accounts', function () {
        Account::factory()->count(3)->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'account_type',
                        'currency',
                        'is_active',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create an account with minimum required fields', function () {
        $data = [
            'name' => 'Test Cash Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
            'is_active' => true,
        ];

        $response = $this->postJson(route('accounts.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'account_type',
                    'currency',
                    'is_active',
                ]
            ]);

        $this->assertDatabaseHas('accounts', [
            'name' => 'Test Cash Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
            'is_active' => true,
        ]);
    });

    it('can create an account with all fields', function () {
        $data = [
            'name' => 'Complete Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
            'opening_balance' => 1500,
            'description' => 'A complete test account with all fields',
            'is_active' => true,
        ];

        $response = $this->postJson(route('accounts.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'name' => 'Complete Account',
                    'description' => 'A complete test account with all fields',
                    'is_active' => true,
                ]
            ]);

        $this->assertDatabaseHas('accounts', [
            'name' => 'Complete Account',
            'description' => 'A complete test account with all fields',
            'is_active' => true,
        ]);
    });

    it('can show an account', function () {
        $account = Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.show', $account));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'description' => $account->description,
                ]
            ]);
    });

    it('can update an account', function () {
        $account = Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $newAccountType = AccountType::factory()->create();
        $newCurrency = Currency::factory()->create();
        
        $data = [
            'name' => 'Updated Account',
            'account_type_id' => $newAccountType->id,
            'currency_id' => $newCurrency->id,
            'description' => 'Updated description',
            'is_active' => false,
        ];

        $response = $this->putJson(route('accounts.update', $account), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Account',
                    'description' => 'Updated description',
                    'is_active' => false,
                ]
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Account',
            'account_type_id' => $newAccountType->id,
            'currency_id' => $newCurrency->id,
            'description' => 'Updated description',
            'is_active' => false,
        ]);
    });

    it('can delete an account', function () {
        $account = Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->deleteJson(route('accounts.destroy', $account));

        $response->assertNoContent();
        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('accounts.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'account_type_id',
                'currency_id',
            ]);
    });

    it('validates unique name constraint', function () {
        Account::factory()->create([
            'name' => 'Duplicate Account Name',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->postJson(route('accounts.store'), [
            'name' => 'Duplicate Account Name',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates foreign key references', function () {
        $response = $this->postJson(route('accounts.store'), [
            'name' => 'Test Account',
            'account_type_id' => 99999,
            'currency_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'account_type_id',
                'currency_id',
            ]);
    });

    it('validates name length constraint', function () {
        $response = $this->postJson(route('accounts.store'), [
            'name' => str_repeat('a', 256), // 256 characters (exceeds 255 limit)
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates description length constraint', function () {
        $longDescription = str_repeat('a', 65536); // Exceeds TEXT field limit
        
        $response = $this->postJson(route('accounts.store'), [
            'name' => 'Test Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
            'description' => $longDescription,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    });

    it('can search accounts by name', function () {
        Account::factory()->create([
            'name' => 'Searchable Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);
        Account::factory()->create([
            'name' => 'Another Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Account');
    });

    it('can search accounts by description', function () {
        Account::factory()->create([
            'name' => 'Account One',
            'description' => 'Special account description',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);
        Account::factory()->create([
            'name' => 'Account Two',
            'description' => 'Regular description',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.index', ['search' => 'Special']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['description'])->toBe('Special account description');
    });

    it('can filter by active status', function () {
        Account::factory()->create([
            'is_active' => true,
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);
        Account::factory()->create([
            'is_active' => false,
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can filter by account type', function () {
        $assetsType = AccountType::factory()->create(['name' => 'Assets']);
        $liabilitiesType = AccountType::factory()->create(['name' => 'Liabilities']);
        
        Account::factory()->create([
            'account_type_id' => $assetsType->id,
            'currency_id' => $this->currency->id,
        ]);
        Account::factory()->create([
            'account_type_id' => $liabilitiesType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.index', ['account_type_id' => $assetsType->id]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['account_type']['id'])->toBe($assetsType->id);
    });

    it('can filter by currency', function () {
        $usdCurrency = Currency::factory()->create(['code' => 'USD']);
        $eurCurrency = Currency::factory()->create(['code' => 'EUR']);
        
        Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $usdCurrency->id,
        ]);
        Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $eurCurrency->id,
        ]);

        $response = $this->getJson(route('accounts.index', ['currency_id' => $usdCurrency->id]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['currency']['id'])->toBe($usdCurrency->id);
    });

    it('can sort accounts by name', function () {
        Account::factory()->create([
            'name' => 'Z Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);
        Account::factory()->create([
            'name' => 'A Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Account');
        expect($data[1]['name'])->toBe('Z Account');
    });

    it('can list trashed accounts', function () {
        $account = Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);
        $account->delete();

        $response = $this->getJson(route('accounts.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'account_type',
                        'currency',
                        'is_active',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed account', function () {
        $account = Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);
        $account->delete();

        $response = $this->patchJson(route('accounts.restore', $account->id));

        $response->assertOk();
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed account', function () {
        $account = Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);
        $account->delete();

        $response = $this->deleteJson(route('accounts.force-delete', $account->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    });

    it('can get account statistics', function () {
        Account::factory()->count(5)->create([
            'is_active' => true,
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);
        Account::factory()->count(2)->create([
            'is_active' => false,
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_accounts',
                    'active_accounts',
                    'inactive_accounts',
                    'trashed_accounts',
                    'accounts_by_type',
                    'accounts_by_currency',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_accounts'])->toBe(7);
        expect($stats['active_accounts'])->toBe(5);
        expect($stats['inactive_accounts'])->toBe(2);
    });

    it('validates unique name constraint on update', function () {
        $account1 = Account::factory()->create([
            'name' => 'Account One',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);
        $account2 = Account::factory()->create([
            'name' => 'Account Two',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        // Try to update account2 to have same name as account1
        $response = $this->putJson(route('accounts.update', $account2), [
            'name' => 'Account One',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating account with same name', function () {
        $account = Account::factory()->create([
            'name' => 'Same Name Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        // Update account but keep same name
        $response = $this->putJson(route('accounts.update', $account), [
            'name' => 'Same Name Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
            'description' => 'Updated description',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Same Name Account',
            'description' => 'Updated description',
        ]);
    });

    it('sets created_by and updated_by fields automatically', function () {
        $account = Account::factory()->create([
            'name' => 'Test Account',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        expect($account->created_by)->toBe($this->user->id);
        expect($account->updated_by)->toBe($this->user->id);

        // Test update tracking
        $account->update(['name' => 'Updated Account']);
        expect($account->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent account', function () {
        $response = $this->getJson(route('accounts.show', 999));

        $response->assertNotFound();
    });

    it('can paginate accounts', function () {
        Account::factory()->count(7)->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');
        
        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('can handle multiple filters simultaneously', function () {
        $assetsType = AccountType::factory()->create(['name' => 'Assets']);
        $usdCurrency = Currency::factory()->create(['code' => 'USD']);
        
        // Create accounts with different combinations
        Account::factory()->create([
            'name' => 'Active USD Asset Account',
            'account_type_id' => $assetsType->id,
            'currency_id' => $usdCurrency->id,
            'is_active' => true,
        ]);
        Account::factory()->create([
            'name' => 'Inactive USD Asset Account',
            'account_type_id' => $assetsType->id,
            'currency_id' => $usdCurrency->id,
            'is_active' => false,
        ]);
        Account::factory()->create([
            'name' => 'Active EUR Asset Account',
            'account_type_id' => $assetsType->id,
            'currency_id' => $this->currency->id,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('accounts.index', [
            'account_type_id' => $assetsType->id,
            'currency_id' => $usdCurrency->id,
            'is_active' => true,
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Active USD Asset Account');
    });

    it('returns empty result for non-matching filters', function () {
        Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('accounts.index', [
            'account_type_id' => 99999, // Non-existent account type
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(0);
    });

    it('can handle boolean filter for inactive accounts', function () {
        Account::factory()->create([
            'is_active' => true,
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);
        Account::factory()->create([
            'is_active' => false,
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.index', ['is_active' => false]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(false);
    });

    it('can handle case-insensitive search', function () {
        Account::factory()->create([
            'name' => 'UPPERCASE ACCOUNT',
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->getJson(route('accounts.index', ['search' => 'uppercase']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('UPPERCASE ACCOUNT');
    });
});
