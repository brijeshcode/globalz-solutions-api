<?php

use App\Models\Accounts\Account;
use App\Models\Setups\Accounts\AccountType;
use App\Models\Setups\Generals\Currencies\Currency;
use Tests\Feature\Accounts\Accounts\Concerns\HasAccountSetup;

uses(HasAccountSetup::class);

beforeEach(function () {
    $this->setUpAccounts();
});

it('lists accounts with correct structure', function () {
    Account::factory()->count(3)->create([
        'account_type_id' => $this->accountType->id,
        'currency_id'     => $this->currency->id,
    ]);

    $this->getJson(route('accounts.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data'       => ['*' => ['id', 'name', 'description', 'account_type', 'currency', 'is_active']],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('searches accounts by name', function () {
    Account::factory()->create(['name' => 'Searchable Account', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    Account::factory()->create(['name' => 'Another Account', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $data = $this->getJson(route('accounts.index', ['search' => 'Searchable']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Searchable Account');
});

it('searches accounts by description', function () {
    Account::factory()->create(['name' => 'Account One', 'description' => 'Special account description', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    Account::factory()->create(['name' => 'Account Two', 'description' => 'Regular description', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $data = $this->getJson(route('accounts.index', ['search' => 'Special']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['description'])->toBe('Special account description');
});

it('handles case-insensitive search', function () {
    Account::factory()->create(['name' => 'UPPERCASE ACCOUNT', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $data = $this->getJson(route('accounts.index', ['search' => 'uppercase']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('UPPERCASE ACCOUNT');
});

it('filters by active status', function () {
    Account::factory()->create(['is_active' => true, 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    Account::factory()->create(['is_active' => false, 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $data = $this->getJson(route('accounts.index', ['is_active' => true]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['is_active'])->toBe(true);
});

it('filters by inactive status', function () {
    Account::factory()->create(['is_active' => true, 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    Account::factory()->create(['is_active' => false, 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $data = $this->getJson(route('accounts.index', ['is_active' => false]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['is_active'])->toBe(false);
});

it('filters by account type', function () {
    $assetsType      = AccountType::firstOrCreate(['name' => 'Assets'], ['description' => null, 'is_active' => true]);
    $liabilitiesType = AccountType::firstOrCreate(['name' => 'Liabilities'], ['description' => null, 'is_active' => true]);

    Account::factory()->create(['account_type_id' => $assetsType->id, 'currency_id' => $this->currency->id]);
    Account::factory()->create(['account_type_id' => $liabilitiesType->id, 'currency_id' => $this->currency->id]);

    $data = $this->getJson(route('accounts.index', ['account_type_id' => $assetsType->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['account_type']['id'])->toBe($assetsType->id);
});

it('filters by currency', function () {
    $usd = Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
    $eur = Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'is_active' => true]);

    Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $usd->id]);
    Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $eur->id]);

    $data = $this->getJson(route('accounts.index', ['currency_id' => $usd->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['currency']['id'])->toBe($usd->id);
});

it('sorts accounts by name ascending', function () {
    Account::factory()->create(['name' => 'Z Account', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    Account::factory()->create(['name' => 'A Account', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $data = $this->getJson(route('accounts.index', ['sort_by' => 'name', 'sort_direction' => 'asc']))
        ->assertOk()
        ->json('data');

    expect($data[0]['name'])->toBe('A Account')
        ->and($data[1]['name'])->toBe('Z Account');
});

it('paginates accounts', function () {
    Account::factory()->count(7)->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $response = $this->getJson(route('accounts.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});

it('handles multiple filters simultaneously', function () {
    $assetsType = AccountType::firstOrCreate(['name' => 'Assets'], ['description' => null, 'is_active' => true]);
    $usd        = Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);

    Account::factory()->create(['name' => 'Active USD Asset Account', 'account_type_id' => $assetsType->id, 'currency_id' => $usd->id, 'is_active' => true]);
    Account::factory()->create(['name' => 'Inactive USD Asset Account', 'account_type_id' => $assetsType->id, 'currency_id' => $usd->id, 'is_active' => false]);
    Account::factory()->create(['name' => 'Active EUR Asset Account', 'account_type_id' => $assetsType->id, 'currency_id' => $this->currency->id, 'is_active' => true]);

    $data = $this->getJson(route('accounts.index', ['account_type_id' => $assetsType->id, 'currency_id' => $usd->id, 'is_active' => true]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Active USD Asset Account');
});

it('returns empty result for non-matching filters', function () {
    Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id, 'is_active' => true]);

    $data = $this->getJson(route('accounts.index', ['account_type_id' => 99999]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(0);
});
