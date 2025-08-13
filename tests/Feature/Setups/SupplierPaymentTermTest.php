<?php

use App\Models\Setups\SupplierPaymentTerm;
use App\Models\User;

uses()->group('api', 'setup', 'setup.payment_terms', 'supplier_payment_terms');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Supplier Payment Terms API', function () {
    it('can list supplier payment terms', function () {
        SupplierPaymentTerm::factory()->count(3)->create();

        $response = $this->getJson(route('setups.supplierPaymentTerm.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'days',
                        'type',
                        'discount_percentage',
                        'discount_days',
                        'is_active',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'pagination'
            ]);
    });

    it('can create a supplier payment term', function () {
        $data = [
            'name' => 'Net 30',
            'description' => 'Payment due in 30 days',
            'days' => 30,
            'type' => 'net',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.supplierPaymentTerm.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'days',
                    'type',
                    'is_active',
                ]
            ]);

        $this->assertDatabaseHas('supplier_payment_terms', [
            'name' => 'Net 30',
            'description' => 'Payment due in 30 days',
            'days' => 30,
            'type' => 'net',
        ]);
    });

    it('can create a payment term with discount', function () {
        $data = [
            'name' => '2/10 Net 30',
            'description' => '2% discount if paid within 10 days, otherwise net 30',
            'days' => 30,
            'type' => 'net',
            'discount_percentage' => 2.00,
            'discount_days' => 10,
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.supplierPaymentTerm.store'), $data);

        $response->assertCreated();

        $this->assertDatabaseHas('supplier_payment_terms', [
            'name' => '2/10 Net 30',
            'discount_percentage' => 2.00,
            'discount_days' => 10,
        ]);
    });

    it('can show a supplier payment term', function () {
        $paymentTerm = SupplierPaymentTerm::factory()->create();

        $response = $this->getJson(route('setups.supplierPaymentTerm.show', $paymentTerm));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $paymentTerm->id,
                    'name' => $paymentTerm->name,
                ]
            ]);
    });

    it('can update a supplier payment term', function () {
        $paymentTerm = SupplierPaymentTerm::factory()->create();
        $data = [
            'name' => 'Updated Net 45',
            'description' => 'Updated payment terms',
            'days' => 45,
            'type' => 'net',
        ];

        $response = $this->putJson(route('setups.supplierPaymentTerm.update', $paymentTerm), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Net 45',
                    'description' => 'Updated payment terms',
                    'days' => 45,
                ]
            ]);

        $this->assertDatabaseHas('supplier_payment_terms', [
            'id' => $paymentTerm->id,
            'name' => 'Updated Net 45',
            'days' => 45,
        ]);
    });

    it('can delete a supplier payment term', function () {
        $paymentTerm = SupplierPaymentTerm::factory()->create();

        $response = $this->deleteJson(route('setups.supplierPaymentTerm.destroy', $paymentTerm));

        $response->assertNoContent();
        $this->assertSoftDeleted('supplier_payment_terms', ['id' => $paymentTerm->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.supplierPaymentTerm.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingPaymentTerm = SupplierPaymentTerm::factory()->create();

        $response = $this->postJson(route('setups.supplierPaymentTerm.store'), [
            'name' => $existingPaymentTerm->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $paymentTerm1 = SupplierPaymentTerm::factory()->create();
        $paymentTerm2 = SupplierPaymentTerm::factory()->create();

        $response = $this->putJson(route('setups.supplierPaymentTerm.update', $paymentTerm1), [
            'name' => $paymentTerm2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates payment term type', function () {
        $response = $this->postJson(route('setups.supplierPaymentTerm.store'), [
            'name' => 'Test Payment Term',
            'type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });

    it('validates days range', function () {
        $response = $this->postJson(route('setups.supplierPaymentTerm.store'), [
            'name' => 'Test Payment Term',
            'days' => 500, // Exceeds max 365
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['days']);
    });

    it('validates discount percentage range', function () {
        $response = $this->postJson(route('setups.supplierPaymentTerm.store'), [
            'name' => 'Test Payment Term',
            'discount_percentage' => 150, // Exceeds max 100
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_percentage']);
    });

    it('can search supplier payment terms', function () {
        SupplierPaymentTerm::factory()->create(['name' => 'Searchable Net 30']);
        SupplierPaymentTerm::factory()->create(['name' => 'Another Payment Term']);

        $response = $this->getJson(route('setups.supplierPaymentTerm.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Net 30');
    });

    it('can filter by active status', function () {
        SupplierPaymentTerm::factory()->active()->create();
        SupplierPaymentTerm::factory()->inactive()->create();

        $response = $this->getJson(route('setups.supplierPaymentTerm.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort supplier payment terms', function () {
        SupplierPaymentTerm::factory()->create(['name' => 'B Payment Term']);
        SupplierPaymentTerm::factory()->create(['name' => 'A Payment Term']);

        $response = $this->getJson(route('setups.supplierPaymentTerm.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Payment Term');
        expect($data[1]['name'])->toBe('B Payment Term');
    });

    it('returns 404 for non-existent payment term', function () {
        $response = $this->getJson(route('setups.supplierPaymentTerm.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed supplier payment terms', function () {
        $paymentTerm = SupplierPaymentTerm::factory()->create();
        $paymentTerm->delete();

        $response = $this->getJson(route('setups.supplierPaymentTerm.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'days',
                        'type',
                        'is_active',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed payment term', function () {
        $paymentTerm = SupplierPaymentTerm::factory()->create();
        $paymentTerm->delete();

        $response = $this->patchJson(route('setups.supplierPaymentTerm.restore', $paymentTerm->id));

        $response->assertOk();
        $this->assertDatabaseHas('supplier_payment_terms', [
            'id' => $paymentTerm->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed payment term', function () {
        $paymentTerm = SupplierPaymentTerm::factory()->create();
        $paymentTerm->delete();

        $response = $this->deleteJson(route('setups.supplierPaymentTerm.force-delete', $paymentTerm->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('supplier_payment_terms', ['id' => $paymentTerm->id]);
    });

    it('returns 404 when trying to restore non-existent trashed payment term', function () {
        $response = $this->patchJson(route('setups.supplierPaymentTerm.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed payment term', function () {
        $response = $this->deleteJson(route('setups.supplierPaymentTerm.force-delete', 999));

        $response->assertNotFound();
    });

    it('can search in description and type fields', function () {
        SupplierPaymentTerm::factory()->create([
            'name' => 'Term One',
            'description' => 'Special net payment terms',
            'type' => 'net'
        ]);
        SupplierPaymentTerm::factory()->create([
            'name' => 'Term Two', 
            'description' => 'Cash payment required',
            'type' => 'cash_on_delivery'
        ]);

        $response = $this->getJson(route('setups.supplierPaymentTerm.index', ['search' => 'net']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['type'])->toBe('net');
    });

    it('sets created_by when creating payment term', function () {
        $data = [
            'name' => 'Test Payment Term',
            'description' => 'Test Description',
            'days' => 30,
            'type' => 'net',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.supplierPaymentTerm.store'), $data);

        $response->assertCreated();
        
        $this->assertDatabaseHas('supplier_payment_terms', [
            'name' => 'Test Payment Term',
            'created_by' => $this->user->id,
        ]);
    });

    it('sets updated_by when updating payment term', function () {
        $paymentTerm = SupplierPaymentTerm::factory()->create();
        $data = [
            'name' => 'Updated Payment Term',
            'description' => 'Updated Description',
            'days' => 60,
        ];

        $response = $this->putJson(route('setups.supplierPaymentTerm.update', $paymentTerm), $data);

        $response->assertOk();
        
        $this->assertDatabaseHas('supplier_payment_terms', [
            'id' => $paymentTerm->id,
            'name' => 'Updated Payment Term',
            'updated_by' => $this->user->id,
        ]);
    });

    it('can create different payment term types', function () {
        $types = [
            ['type' => 'net', 'days' => 30],
            ['type' => 'due_on_receipt', 'days' => 0],
            ['type' => 'cash_on_delivery', 'days' => 0],
            ['type' => 'advance', 'days' => -15],
            ['type' => 'credit', 'days' => 45],
        ];

        foreach ($types as $index => $typeData) {
            $data = [
                'name' => "Test Payment Term {$index}",
                'type' => $typeData['type'],
                'days' => $typeData['days'],
            ];

            $response = $this->postJson(route('setups.supplierPaymentTerm.store'), $data);

            $response->assertCreated();
            $this->assertDatabaseHas('supplier_payment_terms', $data);
        }
    });
});