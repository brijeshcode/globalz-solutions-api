<?php

use App\Models\Setups\Country;
use App\Models\User;

uses()->group('api', 'setup', 'setup.countries', 'countries');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Countries API', function () {
    it('can list countries', function () {
        Country::factory()->count(3)->create();

        $response = $this->getJson(route('setups.countries.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'iso2',
                        'phone_code',
                        'is_active',
                        'display_name',
                        'full_display',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'pagination'
            ]);
    });

    it('can create a country', function () {
        $data = [
            'name' => 'Test Country',
            'code' => 'TST',
            'iso2' => 'TS',
            'phone_code' => '+999',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.countries.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'code',
                    'iso2',
                    'phone_code',
                    'is_active',
                    'display_name',
                    'full_display',
                ]
            ]);

        $this->assertDatabaseHas('countries', [
            'name' => 'Test Country',
            'code' => 'TST',
            'iso2' => 'TS',
        ]);
    });

    it('can show a country', function () {
        $country = Country::factory()->create();

        $response = $this->getJson(route('setups.countries.show', $country));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $country->id,
                    'name' => $country->name,
                    'code' => $country->code,
                    'iso2' => $country->iso2,
                ]
            ]);
    });

    it('can update a country', function () {
        $country = Country::factory()->create();
        $data = [
            'name' => 'Updated Country',
            'code' => 'UPD',
            'iso2' => 'UP',
            'phone_code' => '+888',
        ];

        $response = $this->putJson(route('setups.countries.update', $country), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Country',
                    'code' => 'UPD',
                    'iso2' => 'UP',
                    'phone_code' => '+888',
                ]
            ]);

        $this->assertDatabaseHas('countries', [
            'id' => $country->id,
            'name' => 'Updated Country',
            'code' => 'UPD',
            'iso2' => 'UP',
        ]);
    });

    it('can delete a country', function () {
        $country = Country::factory()->create();

        $response = $this->deleteJson(route('setups.countries.destroy', $country));

        $response->assertNoContent();
        $this->assertSoftDeleted('countries', ['id' => $country->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.countries.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code', 'iso2']);
    });

    it('validates unique name when creating', function () {
        $existingCountry = Country::factory()->create();

        $response = $this->postJson(route('setups.countries.store'), [
            'name' => $existingCountry->name,
            'code' => 'NEW',
            'iso2' => 'NW',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique code when creating', function () {
        $existingCountry = Country::factory()->create();

        $response = $this->postJson(route('setups.countries.store'), [
            'name' => 'New Country',
            'code' => $existingCountry->code,
            'iso2' => 'NW',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('validates unique iso2 when creating', function () {
        $existingCountry = Country::factory()->create();

        $response = $this->postJson(route('setups.countries.store'), [
            'name' => 'New Country',
            'code' => 'NEW',
            'iso2' => $existingCountry->iso2,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['iso2']);
    });

    it('validates code length when creating', function () {
        $response = $this->postJson(route('setups.countries.store'), [
            'name' => 'Test Country',
            'code' => 'TOOLONG',
            'iso2' => 'TS',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('validates iso2 length when creating', function () {
        $response = $this->postJson(route('setups.countries.store'), [
            'name' => 'Test Country',
            'code' => 'TST',
            'iso2' => 'TOOLONG',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['iso2']);
    });

    it('validates phone code format when creating', function () {
        $response = $this->postJson(route('setups.countries.store'), [
            'name' => 'Test Country',
            'code' => 'TST',
            'iso2' => 'TS',
            'phone_code' => '123', // Missing + prefix
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_code']);
    });

    it('validates unique name when updating', function () {
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();

        $response = $this->putJson(route('setups.countries.update', $country1), [
            'name' => $country2->name,
            'code' => 'UPD',
            'iso2' => 'UP',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique code when updating', function () {
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();

        $response = $this->putJson(route('setups.countries.update', $country1), [
            'name' => 'Updated Country',
            'code' => $country2->code,
            'iso2' => 'UP',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('validates unique iso2 when updating', function () {
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();

        $response = $this->putJson(route('setups.countries.update', $country1), [
            'name' => 'Updated Country',
            'code' => 'UPD',
            'iso2' => $country2->iso2,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['iso2']);
    });

    it('allows updating country with its own name, code and iso2', function () {
        $country = Country::factory()->create(['name' => 'Test Country', 'code' => 'TST', 'iso2' => 'TS']);

        $response = $this->putJson(route('setups.countries.update', $country), [
            'name' => 'Test Country', // Same name should be allowed
            'code' => 'TST', // Same code should be allowed
            'iso2' => 'TS', // Same iso2 should be allowed
            'phone_code' => '+999',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Test Country',
                    'code' => 'TST',
                    'iso2' => 'TS',
                    'phone_code' => '+999',
                ]
            ]);
    });

    it('can search countries', function () {
        Country::factory()->create(['name' => 'Searchable Country']);
        Country::factory()->create(['name' => 'Another Country']);

        $response = $this->getJson(route('setups.countries.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Country');
    });

    it('can filter by active status', function () {
        Country::factory()->active()->create();
        Country::factory()->inactive()->create();

        $response = $this->getJson(route('setups.countries.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort countries', function () {
        Country::factory()->create(['name' => 'B Country']);
        Country::factory()->create(['name' => 'A Country']);

        $response = $this->getJson(route('setups.countries.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Country');
        expect($data[1]['name'])->toBe('B Country');
    });

    it('returns 404 for non-existent country', function () {
        $response = $this->getJson(route('setups.countries.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed countries', function () {
        $country = Country::factory()->create();
        $country->delete();

        $response = $this->getJson(route('setups.countries.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'iso2',
                        'phone_code',
                        'is_active',
                        'display_name',
                        'full_display',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed country', function () {
        $country = Country::factory()->create();
        $country->delete();

        $response = $this->patchJson(route('setups.countries.restore', $country->id));

        $response->assertOk();
        $this->assertDatabaseHas('countries', [
            'id' => $country->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed country', function () {
        $country = Country::factory()->create();
        $country->delete();

        $response = $this->deleteJson(route('setups.countries.force-delete', $country->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('countries', ['id' => $country->id]);
    });

    it('returns 404 when trying to restore non-existent trashed country', function () {
        $response = $this->patchJson(route('setups.countries.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed country', function () {
        $response = $this->deleteJson(route('setups.countries.force-delete', 999));

        $response->assertNotFound();
    });
});