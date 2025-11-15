<?php

use App\Models\Accounts\Account;
use App\Models\Accounts\AccountTransfer;
use App\Models\Setups\Accounts\AccountType;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;

uses()->group('api', 'accounts', 'accounts.transfers');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    // Create related models for testing
    $this->accountType = AccountType::factory()->create();
    $this->currency1 = Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar']);
    $this->currency2 = Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro']);

    $this->fromAccount = Account::factory()->create([
        'name' => 'Source Account',
        'account_type_id' => $this->accountType->id,
        'currency_id' => $this->currency1->id,
        'is_active' => true,
    ]);

    $this->toAccount = Account::factory()->create([
        'name' => 'Destination Account',
        'account_type_id' => $this->accountType->id,
        'currency_id' => $this->currency2->id,
        'is_active' => true,
    ]);

    // Helper method for base account transfer data
    $this->getBaseTransferData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-11-14 10:00:00',
            // code and prefix are auto-generated
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
            'received_amount' => 1000.00,
            'sent_amount' => 950.00,
            'currency_rate' => 0.95,
        ], $overrides);
    };
});

describe('Account Transfers API', function () {
    it('can list account transfers', function () {
        AccountTransfer::factory()->count(3)->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'date',
                        'code',
                        'prefix',
                        'from_account_id',
                        'to_account_id',
                        'from_currency_id',
                        'to_currency_id',
                        'received_amount',
                        'sent_amount',
                        'currency_rate',
                        'note',
                        'from_account',
                        'to_account',
                        'from_currency',
                        'to_currency',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create an account transfer with minimum required fields', function () {
        $data = [
            'date' => '2025-11-14 10:00:00',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
            'received_amount' => 1000.00,
            'sent_amount' => 950.00,
            'currency_rate' => 0.95,
        ];

        $response = $this->postJson(route('accounts.transfers.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'date',
                    'code',
                    'prefix',
                    'from_account',
                    'to_account',
                    'from_currency',
                    'to_currency',
                    'received_amount',
                    'sent_amount',
                    'currency_rate',
                ]
            ]);

        $this->assertDatabaseHas('account_transfers', [
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'received_amount' => 1000.00,
            'sent_amount' => 950.00,
        ]);

        // Verify code was auto-generated
        $transfer = AccountTransfer::where('from_account_id', $this->fromAccount->id)
            ->where('received_amount', 1000.00)
            ->first();
        expect($transfer->code)->not()->toBeNull();
        expect($transfer->prefix)->toBe('TRF');
    });

    it('can create an account transfer with all fields', function () {
        $data = ($this->getBaseTransferData)([
            'note' => 'Complete transfer with all fields',
        ]);
        // dd($data);
        $response = $this->postJson(route('accounts.transfers.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'note' => 'Complete transfer with all fields',
                    'prefix' => 'TRF',
                ]
            ]);

        $this->assertDatabaseHas('account_transfers', [
            'note' => 'Complete transfer with all fields',
            'prefix' => 'TRF',
        ]);

        // Verify code was auto-generated
        $responseData = $response->json('data');
        expect($responseData['code'])->not()->toBeNull();
    });

    it('can show an account transfer', function () {
        $transfer = AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.show', $transfer));
        $response->assertOk()
            ->assertJson([
                "message" =>  "Account transfer retrieved successfully",
                'data' => [
                    'id' => $transfer->id,
                    'code' => $transfer->code,
                    'prefix' => $transfer->prefix,
                ]
            ]);
    });

    it('can update an account transfer', function () {
        $transfer = AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $newFromAccount = Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency1->id,
            'is_active' => true,
        ]);

        $data = ($this->getBaseTransferData)([
            'from_account_id' => $newFromAccount->id,
            'received_amount' => 2000.00,
            'sent_amount' => 1900.00,
            'note' => 'Updated transfer',
        ]);

        $response = $this->putJson(route('accounts.transfers.update', $transfer), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'received_amount' => 2000.00,
                    'sent_amount' => 1900.00,
                    'note' => 'Updated transfer',
                ]
            ]);

        $this->assertDatabaseHas('account_transfers', [
            'id' => $transfer->id,
            'from_account_id' => $newFromAccount->id,
            'received_amount' => 2000.00,
        ]);

        // Code should remain unchanged
        expect($transfer->fresh()->code)->toBe($transfer->code);
    });

    it('can delete an account transfer', function () {
        // Create admin user for delete operation
        $adminUser = User::factory()->create(['role' => 'admin']);
        $this->actingAs($adminUser, 'sanctum');

        $transfer = AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->deleteJson(route('accounts.transfers.destroy', $transfer));

        $response->assertNoContent();
        $this->assertSoftDeleted('account_transfers', ['id' => $transfer->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('accounts.transfers.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
                'from_account_id',
                'to_account_id',
                'from_currency_id',
                'to_currency_id',
                'received_amount',
                'sent_amount',
                'currency_rate',
            ]);
    });

    it('validates foreign key references', function () {
        $response = $this->postJson(route('accounts.transfers.store'), [
            'date' => '2025-11-14 10:00:00',
            'from_account_id' => 99999,
            'to_account_id' => 99999,
            'from_currency_id' => 99999,
            'to_currency_id' => 99999,
            'received_amount' => 1000.00,
            'sent_amount' => 950.00,
            'currency_rate' => 0.95,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'from_account_id',
                'to_account_id',
                'from_currency_id',
                'to_currency_id',
            ]);
    });

    it('validates amounts are numeric and positive', function () {
        $data = ($this->getBaseTransferData)([
            'received_amount' => -1000.00,
            'sent_amount' => -500.00,
        ]);

        $response = $this->postJson(route('accounts.transfers.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['received_amount', 'sent_amount']);
    });

    it('validates currency rate is numeric and positive', function () {
        $data = ($this->getBaseTransferData)([
            'currency_rate' => -1.5,
        ]);

        $response = $this->postJson(route('accounts.transfers.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_rate']);
    });

    it('validates date format', function () {
        $data = ($this->getBaseTransferData)([
            'date' => 'invalid-date',
        ]);

        $response = $this->postJson(route('accounts.transfers.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    });

    it('can search transfers by code', function () {
        $transfer1 = AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        $transfer2 = AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        // Search by the first transfer's code
        $response = $this->getJson(route('accounts.transfers.index', ['search' => $transfer1->code]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe($transfer1->code);
    });

    it('can search transfers by note', function () {
        AccountTransfer::factory()->create([
            'note' => 'Special transfer note',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        AccountTransfer::factory()->create([
            'note' => 'Regular note',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', ['search' => 'Special']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['note'])->toBe('Special transfer note');
    });

    it('can filter by from_account_id', function () {
        AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $otherAccount = Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency1->id,
            'is_active' => true,
        ]);
        AccountTransfer::factory()->create([
            'from_account_id' => $otherAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', ['from_account_id' => $this->fromAccount->id]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['from_account']['id'])->toBe($this->fromAccount->id);
    });

    it('can filter by to_account_id', function () {
        AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $otherAccount = Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency2->id,
            'is_active' => true,
        ]);
        AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $otherAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', ['to_account_id' => $this->toAccount->id]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['to_account']['id'])->toBe($this->toAccount->id);
    });

    it('can filter by from_currency_id', function () {
        AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency2->id,
            'to_currency_id' => $this->currency1->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', ['from_currency_id' => $this->currency1->id]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['from_currency']['id'])->toBe($this->currency1->id);
    });

    it('can filter by to_currency_id', function () {
        AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency2->id,
            'to_currency_id' => $this->currency1->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', ['to_currency_id' => $this->currency2->id]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['to_currency']['id'])->toBe($this->currency2->id);
    });

    it('can filter by date range', function () {
        AccountTransfer::factory()->create([
            'date' => '2025-11-01',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        AccountTransfer::factory()->create([
            'date' => '2025-11-15',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        AccountTransfer::factory()->create([
            'date' => '2025-11-30',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-20',
        ]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(2);
    });

    it('can sort transfers by date', function () {
        AccountTransfer::factory()->create([
            'date' => '2025-11-20',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        AccountTransfer::factory()->create([
            'date' => '2025-11-10',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', [
            'sort_by' => 'date',
            'sort_direction' => 'asc',
        ]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data[0]['date'])->toContain('2025-11-10');
        expect($data[1]['date'])->toContain('2025-11-20');
    });

    it('can sort transfers by received_amount', function () {
        AccountTransfer::factory()->create([
            'received_amount' => 5000.00,
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        AccountTransfer::factory()->create([
            'received_amount' => 1000.00,
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', [
            'sort_by' => 'received_amount',
            'sort_direction' => 'asc',
        ]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data[0]['received_amount'])->toBe('1000.00');
        expect($data[1]['received_amount'])->toBe('5000.00');
    });

    it('can list trashed transfers', function () {
        $transfer = AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        $transfer->delete();

        $response = $this->getJson(route('accounts.transfers.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'date',
                        'code',
                        'from_account',
                        'to_account',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed transfer', function () {
        // Create admin user for restore operation
        $adminUser = User::factory()->create(['role' => 'admin']);
        $this->actingAs($adminUser, 'sanctum');

        $transfer = AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        $transfer->delete();

        $response = $this->patchJson(route('accounts.transfers.restore', $transfer->id));

        $response->assertOk();
        $this->assertDatabaseHas('account_transfers', [
            'id' => $transfer->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed transfer', function () {
        // Create admin user for force delete operation
        $adminUser = User::factory()->create(['role' => 'admin']);
        $this->actingAs($adminUser, 'sanctum');

        $transfer = AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        $transfer->delete();

        $response = $this->deleteJson(route('accounts.transfers.force-delete', $transfer->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('account_transfers', ['id' => $transfer->id]);
    });

    it('can get transfer statistics', function () {
        AccountTransfer::factory()->count(5)->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
            'received_amount' => 1000.00,
            'sent_amount' => 950.00,
        ]);

        $response = $this->getJson(route('accounts.transfers.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_transfers',
                    'trashed_transfers',
                    'total_sent_amount',
                    'total_received_amount',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_transfers'])->toBe(5);
        expect($stats['total_sent_amount'])->toBe(4750);
        expect($stats['total_received_amount'])->toBe(5000);
    });

    it('can paginate transfers', function () {
        AccountTransfer::factory()->count(7)->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('sets created_by and updated_by fields automatically', function () {
        $transfer = AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        expect($transfer->created_by)->toBe($this->user->id);
        expect($transfer->updated_by)->toBe($this->user->id);

        $transfer->update(['note' => 'Updated note']);
        expect($transfer->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent transfer', function () {
        $response = $this->getJson(route('accounts.transfers.show', 999));

        $response->assertNotFound();
    });

    it('can handle multiple filters simultaneously', function () {
        AccountTransfer::factory()->create([
            'date' => '2025-11-15',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $otherFromAccount = Account::factory()->create([
            'account_type_id' => $this->accountType->id,
            'currency_id' => $this->currency1->id,
            'is_active' => true,
        ]);
        AccountTransfer::factory()->create([
            'date' => '2025-11-15',
            'from_account_id' => $otherFromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);
        AccountTransfer::factory()->create([
            'date' => '2025-11-20',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', [
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-16',
        ]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
    });

    it('validates that from_account and to_account cannot be the same', function () {
        $data = ($this->getBaseTransferData)([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->fromAccount->id,
        ]);

        $response = $this->postJson(route('accounts.transfers.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['to_account_id']);
    });

    it('validates note length constraint', function () {
        $data = ($this->getBaseTransferData)([
            'note' => str_repeat('a', 65536), // Exceeds TEXT field limit
        ]);

        $response = $this->postJson(route('accounts.transfers.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);
    });

    it('returns empty result for non-matching filters', function () {
        AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', [
            'from_account_id' => 99999,
        ]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(0);
    });

    it('can handle case-insensitive search', function () {
        $transfer = AccountTransfer::factory()->create([
            'note' => 'UPPERCASE note',
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.index', ['search' => 'uppercase']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['note'])->toBe('UPPERCASE note');
    });

    it('returns proper relationship data in response', function () {
        $transfer = AccountTransfer::factory()->create([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id' => $this->currency2->id,
        ]);

        $response = $this->getJson(route('accounts.transfers.show', $transfer));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'from_account' => ['id', 'name', 'account_type_id', 'currency_id'],
                    'to_account' => ['id', 'name', 'account_type_id', 'currency_id'],
                    'from_currency' => ['id', 'name', 'code', 'symbol', 'calculation_type'],
                    'to_currency' => ['id', 'name', 'code', 'symbol', 'calculation_type'],
                    'created_by' => ['id', 'name'],
                    'updated_by' => ['id', 'name'],
                ]
            ]);

        $data = $response->json('data');
        expect($data['from_account']['id'])->toBe($this->fromAccount->id);
        expect($data['to_account']['id'])->toBe($this->toAccount->id);
        expect($data['from_currency']['code'])->toBe('USD');
        expect($data['to_currency']['code'])->toBe('EUR');
    });
});
