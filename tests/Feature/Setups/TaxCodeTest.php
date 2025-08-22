<?php

use App\Models\Setups\TaxCode;
use App\Models\User;

uses()->group('api', 'setup', 'setup.tax_codes', 'tax_codes');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Tax Codes API', function () {
    it('can list tax codes', function () {
        TaxCode::factory()->count(3)->create();

        $response = $this->getJson(route('setups.tax-codes.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'description',
                        'tax_percent',
                        'type',
                        'is_active',
                        'is_default',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'pagination'
            ]);
    });

    it('can create a tax code', function () {
        $data = [
            'code' => 'VAT15',
            'name' => 'Value Added Tax 15%',
            'description' => 'Standard VAT rate',
            'tax_percent' => 15.00,
            'type' => 'exclusive',
            'is_active' => true,
            'is_default' => false,
        ];

        $response = $this->postJson(route('setups.tax-codes.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'name',
                    'description',
                    'tax_percent',
                    'type',
                    'is_active',
                    'is_default',
                ]
            ]);

        $this->assertDatabaseHas('tax_codes', [
            'code' => 'VAT15',
            'name' => 'Value Added Tax 15%',
            'tax_percent' => 15.00,
        ]);
    });

    it('can show a tax code', function () {
        $taxCode = TaxCode::factory()->create();

        $response = $this->getJson(route('setups.tax-codes.show', $taxCode));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $taxCode->id,
                    'code' => $taxCode->code,
                    'name' => $taxCode->name,
                ]
            ]);
    });

    it('can update a tax code', function () {
        $taxCode = TaxCode::factory()->create();
        $data = [
            'code' => 'UPDATED',
            'name' => 'Updated Tax Code',
            'description' => 'Updated Description',
            'tax_percent' => 20.00,
            'type' => 'inclusive',
        ];

        $response = $this->putJson(route('setups.tax-codes.update', $taxCode), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'code' => 'UPDATED',
                    'name' => 'Updated Tax Code',
                    'description' => 'Updated Description',
                    'tax_percent' => 20.00,
                ]
            ]);

        $this->assertDatabaseHas('tax_codes', [
            'id' => $taxCode->id,
            'code' => 'UPDATED',
            'name' => 'Updated Tax Code',
        ]);
    });

    it('can delete a tax code', function () {
        $taxCode = TaxCode::factory()->create();

        $response = $this->deleteJson(route('setups.tax-codes.destroy', $taxCode));

        $response->assertNoContent();
        $this->assertSoftDeleted('tax_codes', ['id' => $taxCode->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.tax-codes.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'tax_percent', 'type']);
    });

    it('validates unique code when creating', function () {
        $existingTaxCode = TaxCode::factory()->create();

        $response = $this->postJson(route('setups.tax-codes.store'), [
            'code' => $existingTaxCode->code,
            'name' => 'Test Tax Code',
            'tax_percent' => 15.00,
            'type' => 'exclusive',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('validates unique code when updating', function () {
        $taxCode1 = TaxCode::factory()->create();
        $taxCode2 = TaxCode::factory()->create();

        $response = $this->putJson(route('setups.tax-codes.update', $taxCode1), [
            'code' => $taxCode2->code,
            'name' => 'Updated Tax Code',
            'tax_percent' => 15.00,
            'type' => 'exclusive',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('allows updating tax code with its own code', function () {
        $taxCode = TaxCode::factory()->create(['code' => 'TEST', 'name' => 'Test Tax Code']);

        $response = $this->putJson(route('setups.tax-codes.update', $taxCode), [
            'code' => 'TEST', // Same code should be allowed
            'name' => 'Test Tax Code',
            'description' => 'Updated description',
            'tax_percent' => 15.00,
            'type' => 'exclusive',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'code' => 'TEST',
                    'name' => 'Test Tax Code',
                    'description' => 'Updated description',
                ]
            ]);
    });

    it('can search tax codes', function () {
        TaxCode::factory()->create(['code' => 'VAT', 'name' => 'Value Added Tax']);
        TaxCode::factory()->create(['code' => 'GST', 'name' => 'Goods and Services Tax']);

        $response = $this->getJson(route('setups.tax-codes.index', ['search' => 'Value']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Value Added Tax');
    });

    it('can filter by active status', function () {
        TaxCode::factory()->active()->create();
        TaxCode::factory()->inactive()->create();

        $response = $this->getJson(route('setups.tax-codes.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can filter by tax type', function () {
        TaxCode::factory()->create(['type' => 'exclusive']);
        TaxCode::factory()->create(['type' => 'inclusive']);

        $response = $this->getJson(route('setups.tax-codes.index', ['type' => 'exclusive']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['type'])->toBe('exclusive');
    });

    it('can sort tax codes', function () {
        TaxCode::factory()->create(['code' => 'VAT', 'name' => 'Value Added Tax']);
        TaxCode::factory()->create(['code' => 'GST', 'name' => 'Goods and Services Tax']);

        $response = $this->getJson(route('setups.tax-codes.index', [
            'sort_by' => 'code',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['code'])->toBe('GST');
        expect($data[1]['code'])->toBe('VAT');
    });

    it('returns 404 for non-existent tax code', function () {
        $response = $this->getJson(route('setups.tax-codes.show', 999));

        $response->assertNotFound();
    });

    it('can search in description field', function () {
        TaxCode::factory()->create([
            'code' => 'VAT1',
            'name' => 'Tax One',
            'description' => 'Special rate for exports'
        ]);
        TaxCode::factory()->create([
            'code' => 'VAT2',
            'name' => 'Tax Two', 
            'description' => 'Regular domestic rate'
        ]);

        $response = $this->getJson(route('setups.tax-codes.index', ['search' => 'exports']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['description'])->toContain('exports');
    });

    it('sets created_by when creating tax code', function () {
        $data = [
            'code' => 'VAT15',
            'name' => 'Value Added Tax 15%',
            'description' => 'Standard VAT rate',
            'tax_percent' => 15.00,
            'type' => 'exclusive',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.tax-codes.store'), $data);

        $response->assertCreated();
        
        $this->assertDatabaseHas('tax_codes', [
            'code' => 'VAT15',
            'created_by' => $this->user->id,
        ]);
    });

    it('sets updated_by when updating tax code', function () {
        $taxCode = TaxCode::factory()->create();
        $data = [
            'code' => 'UPDATED',
            'name' => 'Updated Tax Code',
            'description' => 'Updated Description',
            'tax_percent' => 20.00,
        ];

        $response = $this->putJson(route('setups.tax-codes.update', $taxCode), $data);

        $response->assertOk();
        
        $this->assertDatabaseHas('tax_codes', [
            'id' => $taxCode->id,
            'code' => 'UPDATED',
            'updated_by' => $this->user->id,
        ]);
    });

    it('can get active tax codes', function () {
        TaxCode::factory()->active()->count(2)->create();
        TaxCode::factory()->inactive()->count(3)->create();

        $response = $this->getJson(route('setups.tax-codes.active'));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(2);
        expect($data[0]['is_active'])->toBe(true);
        expect($data[1]['is_active'])->toBe(true);
    });

    it('can get default tax code', function () {
        TaxCode::factory()->create(['is_default' => false]);
        $defaultTaxCode = TaxCode::factory()->create(['is_default' => true]);

        $response = $this->getJson(route('setups.tax-codes.default'));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $defaultTaxCode->id,
                    'is_default' => true,
                ]
            ]);
    });

    it('returns 404 when no default tax code exists', function () {
        TaxCode::factory()->count(2)->create(['is_default' => false]);

        $response = $this->getJson(route('setups.tax-codes.default'));

        $response->assertNotFound();
    });

    it('can set tax code as default', function () {
        $oldDefault = TaxCode::factory()->create(['is_default' => true]);
        $newDefault = TaxCode::factory()->create(['is_default' => false]);

        $response = $this->patchJson(route('setups.tax-codes.set-default', $newDefault));

        $response->assertOk();
        
        $newDefault->refresh();
        $oldDefault->refresh();
        
        expect($newDefault->is_default)->toBe(true);
        expect($oldDefault->is_default)->toBe(false);
    });

    it('can calculate tax for amount', function () {
        $taxCode = TaxCode::factory()->create(['tax_percent' => 15.00, 'type' => 'exclusive']);

        $response = $this->postJson(route('setups.tax-codes.calculate-tax', $taxCode), [
            'amount' => 100.00,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'base_amount',
                    'tax_amount',
                    'total_amount',
                ]
            ]);
    });

    it('validates amount when calculating tax', function () {
        $taxCode = TaxCode::factory()->create();

        $response = $this->postJson(route('setups.tax-codes.calculate-tax', $taxCode), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    });

    it('can bulk destroy tax codes', function () {
        $taxCodes = TaxCode::factory()->count(3)->create();
        $taxCodeIds = $taxCodes->pluck('id')->toArray();

        $response = $this->postJson(route('setups.tax-codes.bulk-destroy'), [
            'tax_code_ids' => $taxCodeIds,
        ]);

        $response->assertNoContent();
        // $response->assertOk();
        
        foreach ($taxCodeIds as $id) {
            $this->assertSoftDeleted('tax_codes', ['id' => $id]);
        }
    });

    it('validates tax code ids for bulk destroy', function () {
        $response = $this->postJson(route('setups.tax-codes.bulk-destroy'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tax_code_ids']);
    });
});