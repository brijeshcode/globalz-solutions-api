<?php

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use App\Models\Setting;

uses()->group('api', 'customers', 'credit-debit-notes');

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->user, 'sanctum');

    // Clean up any existing notes and settings to avoid conflicts
    CustomerCreditDebitNote::withTrashed()->forceDelete();
    Setting::where('group_name', 'customer_credit_debit_notes')->delete();
    Setting::where('group_name', 'customer_credit_debit_notes')->delete();

    // Create credit note code counter setting (starting from 1000)
    Setting::create([
        'group_name' => 'customer_credit_debit_notes',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Customer credit note code counter starting from 1000'
    ]);

    // Create related models for testing
    $this->customer = Customer::factory()->create(['name' => 'Test Customer','is_active'=> true]);
    $this->currency = Currency::factory()->eur()->create(['is_active' => true]);

    // Helper method for base credit/debit note data
    $this->getBaseCreditNoteData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'prefix' => 'CRN',
            'type' => 'credit',
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.25,
            'amount' => 200.00,
            'amount_usd' => 250.00,
            'note' => 'Test credit note',
        ], $overrides);
    };

    $this->getBaseDebitNoteData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'prefix' => 'DBN',
            'type' => 'debit',
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1.25,
            'amount' => 200.00,
            'amount_usd' => 250.00,
            'note' => 'Test debit note',
        ], $overrides);
    };

    // Helper method to create credit note via API
    $this->createCreditNoteViaApi = function ($overrides = []) {
        $noteData = ($this->getBaseCreditNoteData)($overrides);

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $noteData);
        $response->assertCreated();

        return CustomerCreditDebitNote::latest()->first();
    };

    // Helper method to create debit note via API
    $this->createDebitNoteViaApi = function ($overrides = []) {
        $noteData = ($this->getBaseDebitNoteData)($overrides);

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $noteData);
        $response->assertCreated();

        return CustomerCreditDebitNote::latest()->first();
    };

    // Helper method to create note via API (generic)
    $this->createNoteViaApi = function ($overrides = []) {
        $noteData = ($this->getBaseCreditNoteData)($overrides);

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $noteData);
        $response->assertCreated();

        return CustomerCreditDebitNote::latest()->first();
    };
});

describe('Customer Credit/Debit Notes API', function () {
    it('can list credit/debit notes', function () {
        CustomerCreditDebitNote::withTrashed()->forceDelete();
        ($this->createCreditNoteViaApi)();

        $response = $this->getJson(route('customers.credit-debit-notes.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'note_code',
                        'date',
                        'prefix',
                        'type',
                        'customer',
                        'currency',
                        'amount',
                        'amount_usd',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can create credit note', function () {
        $noteData = ($this->getBaseCreditNoteData)([
            'note' => 'Customer refund for returned items',
        ]);

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $noteData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'type',
                    'customer',
                    'currency'
                ]
            ]);

        // Verify note was created
        $this->assertDatabaseHas('customer_credit_debit_notes', [
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'prefix' => 'CRN',
            'amount' => 200.00,
        ]);
    });

    it('can create debit note', function () {
        $noteData = ($this->getBaseDebitNoteData)([
            'note' => 'Additional charges for customer',
        ]);

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $noteData);

        $response->assertCreated();

        // Verify note was created
        $this->assertDatabaseHas('customer_credit_debit_notes', [
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'prefix' => 'DBN',
            'amount' => 200.00,
        ]);
    });

    it('can show note with all relationships', function () {
        $note = ($this->createNoteViaApi)();

        $response = $this->getJson(route('customers.credit-debit-notes.show', $note));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'note_code',
                    'customer' => ['id', 'name', 'code'],
                    'currency' => ['id', 'name', 'code', 'symbol'],
                    'created_by_user' => ['id', 'name'],
                    'updated_by_user' => ['id', 'name'],
                ]
            ]);
    });

    it('can update note', function () {
        $note = ($this->createNoteViaApi)();

        $updateData = [
            'amount' => 300.00,
            'amount_usd' => 240.00,
            'note' => 'Updated note description',
        ];

        $response = $this->putJson(route('customers.credit-debit-notes.update', $note), $updateData);

        $response->assertOk();

        $note->refresh();
        expect($note->amount)->toBe('300.00');
        expect($note->note)->toBe('Updated note description');
    });

    it('auto-generates note codes when not provided', function () {
        $noteData = ($this->getBaseCreditNoteData)();

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $noteData);

        $response->assertCreated();

        $note = CustomerCreditDebitNote::where('customer_id', $this->customer->id)->first();
        expect($note->code)->not()->toBeNull();
        expect($note->code)->toMatch('/^\d{6}$/'); // 6-digit padded number
    });

    it('can soft delete note (admin only)', function () {
        $note = ($this->createCreditNoteViaApi)();

        $response = $this->deleteJson(route('customers.credit-debit-notes.destroy', $note));

        $response->assertStatus(204);
        $this->assertSoftDeleted('customer_credit_debit_notes', ['id' => $note->id]);
    });

    it('prevents non-admin users from deleting notes', function () {
        $note = ($this->createCreditNoteViaApi)();

        $nonAdminUser = User::factory()->create(['role' => 'salesman']);
        $this->actingAs($nonAdminUser, 'sanctum');

        $response = $this->deleteJson(route('customers.credit-debit-notes.destroy', $note));

        $response->assertForbidden();
        $this->assertDatabaseHas('customer_credit_debit_notes', ['id' => $note->id]);
    });

    it('can list trashed notes', function () {
        $note = ($this->createCreditNoteViaApi)();
        $note->delete();

        $response = $this->getJson(route('customers.credit-debit-notes.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can restore trashed note (admin only)', function () {
        $note = ($this->createCreditNoteViaApi)();
        $note->delete();

        $response = $this->patchJson(route('customers.credit-debit-notes.restore', $note->id));

        $response->assertOk();
        $this->assertDatabaseHas('customer_credit_debit_notes', [
            'id' => $note->id,
            'deleted_at' => null
        ]);
    });

    it('can force delete note (admin only)', function () {
        $note = ($this->createCreditNoteViaApi)();
        $note->delete();

        $response = $this->deleteJson(route('customers.credit-debit-notes.force-delete', $note->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('customer_credit_debit_notes', ['id' => $note->id]);
    });

    it('validates required fields when creating', function () {
        $invalidData = [
            'customer_id' => null,
            'currency_id' => null,
            'type' => 'invalid',
            'prefix' => 'INVALID',
            'amount' => -100, // Negative amount
            'currency_rate' => 0, // Zero rate
        ];

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_id',
                'currency_id',
                'type',
                'prefix',
                'amount',
                'currency_rate'
            ]);
    });

    it('validates prefix matches type', function () {
        $invalidData = ($this->getBaseCreditNoteData)([
            'type' => 'credit',
            'prefix' => 'DBN', // Debit prefix for credit type
        ]);

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['prefix']);
    });

    it('validates currency rate calculation', function () {
        $invalidData = ($this->getBaseCreditNoteData)([
            'amount' => 100.00,
            'amount_usd' => 200.00, // Incorrect USD amount
            'currency_rate' => 1.25,
        ]);

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_usd']);
    });

    it('validates customer is active', function () {
        $inactiveCustomer = Customer::factory()->create(['is_active' => false]);

        $noteData = ($this->getBaseCreditNoteData)([
            'customer_id' => $inactiveCustomer->id,
        ]);

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $noteData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    });

    it('can filter notes by customer', function () {
        $otherCustomer = Customer::factory()->create(['name' => 'Other Customer', 'is_active' => true]);

        ($this->createCreditNoteViaApi)(['customer_id' => $this->customer->id]);
        ($this->createCreditNoteViaApi)(['customer_id' => $otherCustomer->id]);

        $response = $this->getJson(route('customers.credit-debit-notes.index', ['customer_id' => $this->customer->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter notes by currency', function () {
        $otherCurrency = Currency::factory()->usd()->create(['is_active' => true]);

        ($this->createCreditNoteViaApi)(['currency_id' => $this->currency->id]);
        ($this->createCreditNoteViaApi)(['currency_id' => $otherCurrency->id]);

        $response = $this->getJson(route('customers.credit-debit-notes.index', ['currency_id' => $this->currency->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter notes by type', function () {
        ($this->createCreditNoteViaApi)();
        ($this->createDebitNoteViaApi)();

        $response = $this->getJson(route('customers.credit-debit-notes.index', ['type' => 'credit']));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter notes by prefix', function () {
        ($this->createCreditNoteViaApi)(['prefix' => 'CRN']);
        ($this->createDebitNoteViaApi)(['prefix' => 'DBN']);

        $response = $this->getJson(route('customers.credit-debit-notes.index', ['prefix' => 'CRN']));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter notes by date range', function () {
        ($this->createCreditNoteViaApi)(['date' => '2025-01-01']);
        ($this->createCreditNoteViaApi)(['date' => '2025-02-15']);
        ($this->createCreditNoteViaApi)(['date' => '2025-03-30']);

        $response = $this->getJson(route('customers.credit-debit-notes.index', [
            'start_date' => '2025-02-01',
            'end_date' => '2025-02-28'
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can search notes by code', function () {
        CustomerCreditDebitNote::withTrashed()->forceDelete();

        $note1 = ($this->createCreditNoteViaApi)();
        $note2 = ($this->createCreditNoteViaApi)();

        // Search for the first note's code
        $response = $this->getJson(route('customers.credit-debit-notes.index', ['search' => $note1->code]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe($note1->code);
    });

    it('can search notes by note content', function () {
        ($this->createCreditNoteViaApi)(['note' => 'Special refund for damaged goods']);
        ($this->createCreditNoteViaApi)(['note' => 'Regular adjustment']);

        $response = $this->getJson(route('customers.credit-debit-notes.index', ['search' => 'damaged goods']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['note'])->toContain('damaged goods');
    });

    it('sets created_by and updated_by fields automatically', function () {
        $note = ($this->createCreditNoteViaApi)();

        expect($note->created_by)->toBe($this->user->id);
        expect($note->updated_by)->toBe($this->user->id);

        $note->update(['note' => 'Updated note']);
        expect($note->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent note', function () {
        $response = $this->getJson(route('customers.credit-debit-notes.show', 999));

        $response->assertNotFound();
    });

    it('can paginate notes', function () {
        for ($i = 0; $i < 7; $i++) {
            ($this->createCreditNoteViaApi)();
        }

        $response = $this->getJson(route('customers.credit-debit-notes.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('can get note statistics', function () {
        // Clean up existing notes to avoid code conflicts
        CustomerCreditDebitNote::withTrashed()->forceDelete();

        for ($i = 0; $i < 3; $i++) {
            ($this->createCreditNoteViaApi)([
                'amount' => 100.00,
                'amount_usd' => 125.00,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            ($this->createDebitNoteViaApi)([
                'amount' => 150.00,
                'amount_usd' => 187.5,
            ]);
        }

        $response = $this->getJson(route('customers.credit-debit-notes.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_notes',
                    'credit_notes',
                    'debit_notes',
                    'trashed_notes',
                    'total_credit_amount',
                    'total_debit_amount',
                    'total_credit_amount_usd',
                    'total_debit_amount_usd',
                    'notes_by_prefix',
                    'notes_by_currency',
                    'recent_notes',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_notes'])->toBe(5);
        expect($stats['credit_notes'])->toBe(3);
        expect($stats['debit_notes'])->toBe(2);
    });

    it('only allows admin users to create notes', function () {
        $nonAdminUser = User::factory()->create(['role' => 'salesman']);
        $this->actingAs($nonAdminUser, 'sanctum');

        $noteData = ($this->getBaseCreditNoteData)();

        $response = $this->postJson(route('customers.credit-debit-notes.store'), $noteData);

        $response->assertForbidden();
    });

    it('only allows admin users to update notes', function () {
        $note = ($this->createCreditNoteViaApi)();

        $nonAdminUser = User::factory()->create(['role' => 'salesman']);
        $this->actingAs($nonAdminUser, 'sanctum');

        $updateData = [
            'amount' => 300.00,
            'amount_usd' => 240.00,
        ];

        $response = $this->putJson(route('customers.credit-debit-notes.update', $note), $updateData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['authorization']);
    });
});

describe('Note Code Generation Tests', function () {
    it('creates credit note with current counter and increments correctly', function () {
        Setting::set('customer_credit_debit_notes', 'code_counter', 1005, 'number');
        $this->customer->update(['is_active' => true]);

        $response = $this->postJson(route('customers.credit-debit-notes.store'), ($this->getBaseCreditNoteData)());
        $response->assertCreated();
        $code = $response->json('data.code');
        expect($code)->toBe('001006');
    });

    it('creates debit note with current counter and increments correctly', function () {
        Setting::set('customer_credit_debit_notes', 'code_counter', 2005, 'number');
        $this->customer->update(['is_active' => true]);

        $response = $this->postJson(route('customers.credit-debit-notes.store'), ($this->getBaseDebitNoteData)());

        $response->assertCreated();
        $code = $response->json('data.code');
        expect($code)->toBe('002006');
    });

    it('generates sequential credit note codes', function () {
        // Clean state for this specific test
        CustomerCreditDebitNote::withTrashed()->forceDelete();
        Setting::where('group_name', 'customer_credit_debit_notes')->delete();
        Setting::create([
            'group_name' => 'customer_credit_debit_notes',
            'key_name' => 'code_counter',
            'value' => '2000',
            'data_type' => 'number',
            'description' => 'Test counter'
        ]);
        $this->customer->update(['is_active' => true]);

        $response1 = $this->postJson(route('customers.credit-debit-notes.store'), ($this->getBaseCreditNoteData)());
        $response1->assertCreated();
        $code1 = $response1->json('data.code');

        $response2 = $this->postJson(route('customers.credit-debit-notes.store'), ($this->getBaseCreditNoteData)());
        $response2->assertCreated();
        $code2 = $response2->json('data.code');

        $num1 = (int) $code1;
        $num2 = (int) $code2;
        expect($num2)->toBe($num1 + 1);
    });

    it('generates sequential debit note codes', function () {
        // Clean state for this specific test
        CustomerCreditDebitNote::withTrashed()->forceDelete();
        Setting::where('group_name', 'customer_credit_debit_notes')->delete();
        Setting::create([
            'group_name' => 'customer_credit_debit_notes',
            'key_name' => 'code_counter',
            'value' => '3000',
            'data_type' => 'number',
            'description' => 'Test counter'
        ]);
        $this->customer->update(['is_active' => true]);

        $response1 = $this->postJson(route('customers.credit-debit-notes.store'), ($this->getBaseDebitNoteData)());
        $response1->assertCreated();
        $code1 = $response1->json('data.code');

        $response2 = $this->postJson(route('customers.credit-debit-notes.store'), ($this->getBaseDebitNoteData)());
        $response2->assertCreated();
        $code2 = $response2->json('data.code');

        $num1 = (int) $code1;
        $num2 = (int) $code2;
        expect($num2)->toBe($num1 + 1);
    });
});
