<?php

use App\Models\Setups\Supplier;
use App\Models\Suppliers\SupplierCreditDebitNote;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use App\Models\Setting;

uses()->group('api', 'suppliers', 'credit-debit-notes');

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->user, 'sanctum');

    // Clean up any existing notes and settings to avoid conflicts
    SupplierCreditDebitNote::withTrashed()->forceDelete();
    Setting::where('group_name', 'supplier_credit_debit_notes')->delete();

    // Create credit note code counter setting (starting from 1000)
    Setting::create([
        'group_name' => 'supplier_credit_debit_notes',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Supplier credit note code counter starting from 1000'
    ]);

    // Create related models for testing
    $this->supplier = Supplier::factory()->create(['name' => 'Test Supplier', 'is_active' => true]);
    $this->currency = Currency::factory()->eur()->create(['is_active' => true]);

    // Helper method for base credit/debit note data
    $this->getBaseCreditNoteData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'prefix' => 'SCRN',
            'type' => 'credit',
            'supplier_id' => $this->supplier->id,
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
            'prefix' => 'SDRN',
            'type' => 'debit',
            'supplier_id' => $this->supplier->id,
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

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $noteData);
        $response->assertCreated();

        return SupplierCreditDebitNote::latest()->first();
    };

    // Helper method to create debit note via API
    $this->createDebitNoteViaApi = function ($overrides = []) {
        $noteData = ($this->getBaseDebitNoteData)($overrides);

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $noteData);
        $response->assertCreated();

        return SupplierCreditDebitNote::latest()->first();
    };

    // Helper method to create note via API (generic)
    $this->createNoteViaApi = function ($overrides = []) {
        $noteData = ($this->getBaseCreditNoteData)($overrides);

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $noteData);
        $response->assertCreated();

        return SupplierCreditDebitNote::latest()->first();
    };
});

describe('Supplier Credit/Debit Notes API', function () {
    it('can list credit/debit notes', function () {
        SupplierCreditDebitNote::withTrashed()->forceDelete();
        ($this->createCreditNoteViaApi)();

        $response = $this->getJson(route('suppliers.credit-debit-notes.index'));

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
                        'supplier',
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
            'note' => 'Supplier refund for returned items',
        ]);

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $noteData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'type',
                    'supplier',
                    'currency'
                ]
            ]);

        // Verify note was created
        $this->assertDatabaseHas('supplier_credit_debit_notes', [
            'supplier_id' => $this->supplier->id,
            'type' => 'credit',
            'prefix' => 'SCRN',
            'amount' => 200.00,
        ]);
    });

    it('can create debit note', function () {
        $noteData = ($this->getBaseDebitNoteData)([
            'note' => 'Additional charges for supplier',
        ]);

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $noteData);

        $response->assertCreated();

        // Verify note was created
        $this->assertDatabaseHas('supplier_credit_debit_notes', [
            'supplier_id' => $this->supplier->id,
            'type' => 'debit',
            'prefix' => 'SDRN',
            'amount' => 200.00,
        ]);
    });

    it('can show note with all relationships', function () {
        $note = ($this->createNoteViaApi)();

        $response = $this->getJson(route('suppliers.credit-debit-notes.show', $note));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'note_code',
                    'supplier' => ['id', 'name', 'code'],
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

        $response = $this->putJson(route('suppliers.credit-debit-notes.update', $note), $updateData);

        $response->assertOk();

        $note->refresh();
        expect($note->amount)->toBe('300.00');
        expect($note->note)->toBe('Updated note description');
    });

    it('auto-generates note codes when not provided', function () {
        $noteData = ($this->getBaseCreditNoteData)();

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $noteData);

        $response->assertCreated();

        $note = SupplierCreditDebitNote::where('supplier_id', $this->supplier->id)->first();
        expect($note->code)->not()->toBeNull();
        expect($note->code)->toMatch('/^\d{6}$/'); // 6-digit padded number
    });

    it('can soft delete note (admin only)', function () {
        $note = ($this->createCreditNoteViaApi)();

        $response = $this->deleteJson(route('suppliers.credit-debit-notes.destroy', $note));

        $response->assertStatus(204);
        $this->assertSoftDeleted('supplier_credit_debit_notes', ['id' => $note->id]);
    });

    it('prevents non-admin users from deleting notes', function () {
        $note = ($this->createCreditNoteViaApi)();

        $nonAdminUser = User::factory()->create(['role' => 'salesman']);
        $this->actingAs($nonAdminUser, 'sanctum');

        $response = $this->deleteJson(route('suppliers.credit-debit-notes.destroy', $note));

        $response->assertForbidden();
        $this->assertDatabaseHas('supplier_credit_debit_notes', ['id' => $note->id]);
    });

    it('can list trashed notes', function () {
        $note = ($this->createCreditNoteViaApi)();
        $note->delete();

        $response = $this->getJson(route('suppliers.credit-debit-notes.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can restore trashed note (admin only)', function () {
        $note = ($this->createCreditNoteViaApi)();
        $note->delete();

        $response = $this->patchJson(route('suppliers.credit-debit-notes.restore', $note->id));

        $response->assertOk();
        $this->assertDatabaseHas('supplier_credit_debit_notes', [
            'id' => $note->id,
            'deleted_at' => null
        ]);
    });

    it('can force delete note (admin only)', function () {
        $note = ($this->createCreditNoteViaApi)();
        $note->delete();

        $response = $this->deleteJson(route('suppliers.credit-debit-notes.force-delete', $note->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('supplier_credit_debit_notes', ['id' => $note->id]);
    });

    it('validates required fields when creating', function () {
        $invalidData = [
            'supplier_id' => null,
            'currency_id' => null,
            'type' => 'invalid',
            'prefix' => 'INVALID',
            'amount' => -100, // Negative amount
            'currency_rate' => 0, // Zero rate
        ];

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'supplier_id',
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
            'prefix' => 'SDRN', // Debit prefix for credit type
        ]);

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['prefix']);
    });

    it('validates currency rate calculation', function () {
        $invalidData = ($this->getBaseCreditNoteData)([
            'amount' => 100.00,
            'amount_usd' => 200.00, // Incorrect USD amount
            'currency_rate' => 1.25,
        ]);

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_usd']);
    });

    it('validates supplier is active', function () {
        $inactiveSupplier = Supplier::factory()->create(['is_active' => false]);

        $noteData = ($this->getBaseCreditNoteData)([
            'supplier_id' => $inactiveSupplier->id,
        ]);

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $noteData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id']);
    });

    it('can filter notes by supplier', function () {
        $otherSupplier = Supplier::factory()->create(['name' => 'Other Supplier', 'is_active' => true]);

        ($this->createCreditNoteViaApi)(['supplier_id' => $this->supplier->id]);
        ($this->createCreditNoteViaApi)(['supplier_id' => $otherSupplier->id]);

        $response = $this->getJson(route('suppliers.credit-debit-notes.index', ['supplier_id' => $this->supplier->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter notes by currency', function () {
        $otherCurrency = Currency::factory()->usd()->create(['is_active' => true]);

        ($this->createCreditNoteViaApi)(['currency_id' => $this->currency->id]);
        ($this->createCreditNoteViaApi)(['currency_id' => $otherCurrency->id]);

        $response = $this->getJson(route('suppliers.credit-debit-notes.index', ['currency_id' => $this->currency->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter notes by type', function () {
        ($this->createCreditNoteViaApi)();
        ($this->createDebitNoteViaApi)();

        $response = $this->getJson(route('suppliers.credit-debit-notes.index', ['type' => 'credit']));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter notes by prefix', function () {
        ($this->createCreditNoteViaApi)(['prefix' => 'SCRN']);
        ($this->createDebitNoteViaApi)(['prefix' => 'SDRN']);

        $response = $this->getJson(route('suppliers.credit-debit-notes.index', ['prefix' => 'SCRN']));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter notes by date range', function () {
        ($this->createCreditNoteViaApi)(['date' => '2025-01-01']);
        ($this->createCreditNoteViaApi)(['date' => '2025-02-15']);
        ($this->createCreditNoteViaApi)(['date' => '2025-03-30']);

        $response = $this->getJson(route('suppliers.credit-debit-notes.index', [
            'start_date' => '2025-02-01',
            'end_date' => '2025-02-28'
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can search notes by code', function () {
        SupplierCreditDebitNote::withTrashed()->forceDelete();

        $note1 = ($this->createCreditNoteViaApi)();
        $note2 = ($this->createCreditNoteViaApi)();

        // Search for the first note's code
        $response = $this->getJson(route('suppliers.credit-debit-notes.index', ['search' => $note1->code]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe($note1->code);
    });

    it('can search notes by note content', function () {
        ($this->createCreditNoteViaApi)(['note' => 'Special refund for damaged goods']);
        ($this->createCreditNoteViaApi)(['note' => 'Regular adjustment']);

        $response = $this->getJson(route('suppliers.credit-debit-notes.index', ['search' => 'damaged goods']));

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
        $response = $this->getJson(route('suppliers.credit-debit-notes.show', 999));

        $response->assertNotFound();
    });

    it('can paginate notes', function () {
        for ($i = 0; $i < 7; $i++) {
            ($this->createCreditNoteViaApi)();
        }

        $response = $this->getJson(route('suppliers.credit-debit-notes.index', ['per_page' => 3]));

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
        SupplierCreditDebitNote::withTrashed()->forceDelete();

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

        $response = $this->getJson(route('suppliers.credit-debit-notes.stats'));

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

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), $noteData);

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

        $response = $this->putJson(route('suppliers.credit-debit-notes.update', $note), $updateData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['authorization']);
    });
});

describe('Note Code Generation Tests', function () {
    it('creates credit note with current counter and increments correctly', function () {
        Setting::set('supplier_credit_debit_notes', 'code_counter', 1005, 'number');
        $this->supplier->update(['is_active' => true]);

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), ($this->getBaseCreditNoteData)());
        $response->assertCreated();
        $code = $response->json('data.code');
        expect($code)->toBe('001006');
    });

    it('creates debit note with current counter and increments correctly', function () {
        Setting::set('supplier_credit_debit_notes', 'code_counter', 2005, 'number');
        $this->supplier->update(['is_active' => true]);

        $response = $this->postJson(route('suppliers.credit-debit-notes.store'), ($this->getBaseDebitNoteData)());

        $response->assertCreated();
        $code = $response->json('data.code');
        expect($code)->toBe('002006');
    });

    it('generates sequential credit note codes', function () {
        // Clean state for this specific test
        SupplierCreditDebitNote::withTrashed()->forceDelete();
        Setting::where('group_name', 'supplier_credit_debit_notes')->delete();
        Setting::create([
            'group_name' => 'supplier_credit_debit_notes',
            'key_name' => 'code_counter',
            'value' => '2000',
            'data_type' => 'number',
            'description' => 'Test counter'
        ]);
        $this->supplier->update(['is_active' => true]);

        $response1 = $this->postJson(route('suppliers.credit-debit-notes.store'), ($this->getBaseCreditNoteData)());
        $response1->assertCreated();
        $code1 = $response1->json('data.code');

        $response2 = $this->postJson(route('suppliers.credit-debit-notes.store'), ($this->getBaseCreditNoteData)());
        $response2->assertCreated();
        $code2 = $response2->json('data.code');

        $num1 = (int) $code1;
        $num2 = (int) $code2;
        expect($num2)->toBe($num1 + 1);
    });

    it('generates sequential debit note codes', function () {
        // Clean state for this specific test
        SupplierCreditDebitNote::withTrashed()->forceDelete();
        Setting::where('group_name', 'supplier_credit_debit_notes')->delete();
        Setting::create([
            'group_name' => 'supplier_credit_debit_notes',
            'key_name' => 'code_counter',
            'value' => '3000',
            'data_type' => 'number',
            'description' => 'Test counter'
        ]);
        $this->supplier->update(['is_active' => true]);

        $response1 = $this->postJson(route('suppliers.credit-debit-notes.store'), ($this->getBaseDebitNoteData)());
        $response1->assertCreated();
        $code1 = $response1->json('data.code');

        $response2 = $this->postJson(route('suppliers.credit-debit-notes.store'), ($this->getBaseDebitNoteData)());
        $response2->assertCreated();
        $code2 = $response2->json('data.code');

        $num1 = (int) $code1;
        $num2 = (int) $code2;
        expect($num2)->toBe($num1 + 1);
    });
});
