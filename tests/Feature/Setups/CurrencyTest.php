<?php

use App\Models\Setups\Currency;
use App\Models\User;

uses()->group('api', 'setup', 'setup.currencies', 'currencies');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Currencies API', function () {
    it('can list currencies', function () {
        Currency::factory()->count(3)->create();

        $response = $this->getJson(route('setups.currencies.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'symbol',
                        'symbol_position',
                        'decimal_places',
                        'decimal_separator',
                        'thousand_separator',
                        'is_active',
                        'display_name',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'pagination'
            ]);
    });

    it('can create a currency', function () {
        $data = [
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousand_separator' => ',',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.currencies.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'code',
                    'symbol',
                    'symbol_position',
                    'decimal_places',
                    'decimal_separator',
                    'thousand_separator',
                    'is_active',
                    'display_name',
                ]
            ]);

        $this->assertDatabaseHas('currencies', [
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
        ]);
    });

    it('can show a currency', function () {
        $currency = Currency::factory()->create();

        $response = $this->getJson(route('setups.currencies.show', $currency));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $currency->id,
                    'name' => $currency->name,
                    'code' => $currency->code,
                ]
            ]);
    });

    it('can update a currency', function () {
        $currency = Currency::factory()->create();
        $data = [
            'name' => 'Updated Currency',
            'code' => 'UPD',
            'symbol' => '∪',
            'symbol_position' => 'after',
            'decimal_places' => 3,
        ];

        $response = $this->putJson(route('setups.currencies.update', $currency), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Currency',
                    'code' => 'UPD',
                    'symbol' => '∪',
                    'symbol_position' => 'after',
                    'decimal_places' => 3,
                ]
            ]);

        $this->assertDatabaseHas('currencies', [
            'id' => $currency->id,
            'name' => 'Updated Currency',
            'code' => 'UPD',
        ]);
    });

    it('can delete a currency', function () {
        $currency = Currency::factory()->create();

        $response = $this->deleteJson(route('setups.currencies.destroy', $currency));

        $response->assertNoContent();
        $this->assertSoftDeleted('currencies', ['id' => $currency->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.currencies.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code']);
    });

    it('validates unique name when creating', function () {
        $existingCurrency = Currency::factory()->create();

        $response = $this->postJson(route('setups.currencies.store'), [
            'name' => $existingCurrency->name,
            'code' => 'NEW',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique code when creating', function () {
        $existingCurrency = Currency::factory()->create();

        $response = $this->postJson(route('setups.currencies.store'), [
            'name' => 'New Currency',
            'code' => $existingCurrency->code,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('validates code length when creating', function () {
        $response = $this->postJson(route('setups.currencies.store'), [
            'name' => 'Test Currency',
            'code' => 'TOOLONG',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('validates symbol position enum when creating', function () {
        $response = $this->postJson(route('setups.currencies.store'), [
            'name' => 'Test Currency',
            'code' => 'TST',
            'symbol_position' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol_position']);
    });

    it('validates decimal places range when creating', function () {
        $response = $this->postJson(route('setups.currencies.store'), [
            'name' => 'Test Currency',
            'code' => 'TST',
            'decimal_places' => 15,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['decimal_places']);
    });

    it('validates unique name when updating', function () {
        $currency1 = Currency::factory()->create();
        $currency2 = Currency::factory()->create();

        $response = $this->putJson(route('setups.currencies.update', $currency1), [
            'name' => $currency2->name,
            'code' => 'UPD',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique code when updating', function () {
        $currency1 = Currency::factory()->create();
        $currency2 = Currency::factory()->create();

        $response = $this->putJson(route('setups.currencies.update', $currency1), [
            'name' => 'Updated Currency',
            'code' => $currency2->code,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('can search currencies', function () {
        Currency::factory()->create(['name' => 'Searchable Currency']);
        Currency::factory()->create(['name' => 'Another Currency']);

        $response = $this->getJson(route('setups.currencies.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Currency');
    });

    it('can filter by active status', function () {
        Currency::factory()->active()->create();
        Currency::factory()->inactive()->create();

        $response = $this->getJson(route('setups.currencies.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort currencies', function () {
        Currency::factory()->create(['name' => 'B Currency']);
        Currency::factory()->create(['name' => 'A Currency']);

        $response = $this->getJson(route('setups.currencies.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Currency');
        expect($data[1]['name'])->toBe('B Currency');
    });

    it('returns 404 for non-existent currency', function () {
        $response = $this->getJson(route('setups.currencies.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed currencies', function () {
        $currency = Currency::factory()->create();
        $currency->delete();

        $response = $this->getJson(route('setups.currencies.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'symbol',
                        'symbol_position',
                        'decimal_places',
                        'decimal_separator',
                        'thousand_separator',
                        'is_active',
                        'display_name',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed currency', function () {
        $currency = Currency::factory()->create();
        $currency->delete();

        $response = $this->patchJson(route('setups.currencies.restore', $currency->id));

        $response->assertOk();
        $this->assertDatabaseHas('currencies', [
            'id' => $currency->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed currency', function () {
        $currency = Currency::factory()->create();
        $currency->delete();

        $response = $this->deleteJson(route('setups.currencies.force-delete', $currency->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
    });

    it('returns 404 when trying to restore non-existent trashed currency', function () {
        $response = $this->patchJson(route('setups.currencies.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed currency', function () {
        $response = $this->deleteJson(route('setups.currencies.force-delete', 999));

        $response->assertNotFound();
    });
});