<?php

use App\Models\Items\Item;
use App\Models\Setting;
use Tests\Feature\Items\Items\Concerns\HasItemSetup;

uses(HasItemSetup::class);

beforeEach(function () {
    $this->setUpItems();
});

it('gets next available code', function () {
    $data = $this->getJson(route('setups.items.next-code'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['code', 'is_available', 'message']])
        ->json('data');

    expect((int) $data['code'])->toBeGreaterThanOrEqual(5000)
        ->and($data['is_available'])->toBe(true);
});

it('checks code availability for available code', function () {
    $this->postJson(route('setups.items.check-code'), ['code' => 'AVAILABLE-CODE-5000'])
        ->assertOk()
        ->assertJson(['data' => ['code' => 'AVAILABLE-CODE-5000', 'is_available' => true]]);
});

it('detects unavailable codes and suggests alternative', function () {
    $this->createItem(['code' => 'TAKEN-CODE']);

    $response = $this->postJson(route('setups.items.check-code'), ['code' => 'TAKEN-CODE'])
        ->assertOk()
        ->assertJson(['data' => ['code' => 'TAKEN-CODE', 'is_available' => false]]);

    expect($response->json('data.suggested_code'))->not()->toBeNull();
});

it('extracts numeric parts correctly from various code formats', function () {
    Setting::set('items', 'code_counter', 5000, 'number');

    $cases = [
        ['code' => 'a5001',            'numeric_part' => '5001'],
        ['code' => '5002a',            'numeric_part' => '5002'],
        ['code' => '50aa03',           'numeric_part' => '5003'],
        ['code' => 'prefix-5004-suffix', 'numeric_part' => '5004'],
        ['code' => '999',              'numeric_part' => '999'],
        ['code' => 'no-numbers',       'numeric_part' => null],
    ];

    foreach ($cases as $case) {
        $response = $this->postJson(route('setups.items.check-code'), ['code' => $case['code']])->assertOk();
        expect($response->json('data.numeric_part'))->toBe($case['numeric_part']);
    }
});

it('returns next code when counter is at 5001', function () {
    Setting::set('items', 'code_counter', 5001, 'number');

    $code = $this->getJson(route('setups.items.next-code'))->assertOk()->json('data.code');

    expect((int) $code)->toBe(5001);
});

it('increments counter when item with same numeric part is created', function () {
    Setting::set('items', 'code_counter', 5001, 'number');

    $this->postJson(route('setups.items.store'), $this->itemPayload(['code' => 'a5001', 'short_name' => 'Custom Code Item']))->assertCreated();

    expect((int) $this->getJson(route('setups.items.next-code'))->json('data.code'))->toBe(5002);
});

it('increments to 5003 when code 5002a is created', function () {
    Setting::set('items', 'code_counter', 5002, 'number');

    $this->postJson(route('setups.items.store'), $this->itemPayload(['code' => '5002a', 'short_name' => 'Custom Code Item']))->assertCreated();

    expect((int) $this->getJson(route('setups.items.next-code'))->json('data.code'))->toBe(5003);
});

it('increments to 5004 when code 50aa03 is created', function () {
    Setting::set('items', 'code_counter', 5003, 'number');

    $this->postJson(route('setups.items.store'), $this->itemPayload(['code' => '50aa03', 'short_name' => 'Custom Code Item']))->assertCreated();

    expect((int) $this->getJson(route('setups.items.next-code'))->json('data.code'))->toBe(5004);
});

it('rejects custom codes with numeric part less than current counter', function () {
    Setting::set('items', 'code_counter', 5005, 'number');

    $this->postJson(route('setups.items.store'), $this->itemPayload(['code' => 'a5004', 'short_name' => 'Old Code Item']))
        ->assertUnprocessable();
});

it('rejects custom codes with numeric part greater than current counter', function () {
    Setting::set('items', 'code_counter', 5015, 'number');

    $this->postJson(route('setups.items.store'), $this->itemPayload(['code' => 'prefix-5020', 'short_name' => 'Future Code Item']))
        ->assertUnprocessable();
});

it('rejects codes without numeric parts', function () {
    Setting::set('items', 'code_counter', 5020, 'number');

    $this->postJson(route('setups.items.store'), $this->itemPayload(['code' => 'no-numbers-here', 'short_name' => 'Non-numeric Code Item']))
        ->assertUnprocessable();
});

it('auto-creates code counter setting when missing', function () {
    Setting::where('group_name', 'items')->where('key_name', 'code_counter')->delete();
    Setting::clearCache();

    $nextCode = $this->getJson(route('setups.items.next-code'))->assertOk()->json('data.code');

    expect((int) $nextCode)->toBe(5000);

    $setting = Setting::where('group_name', 'items')->where('key_name', 'code_counter')->first();
    expect($setting)->not()->toBeNull()
        ->and($setting->data_type)->toBe('number')
        ->and($setting->value)->toBe('5000');
});

it('creates item with suggested code and increments counter', function () {
    Setting::set('items', 'code_counter', 5010, 'number');

    $suggestedCode = $this->getJson(route('setups.items.next-code'))->json('data.code');
    expect((int) $suggestedCode)->toBe(5010);

    $this->postJson(route('setups.items.store'), $this->itemPayload(['code' => $suggestedCode, 'short_name' => 'Suggested Code Item']))->assertCreated();

    expect((int) $this->getJson(route('setups.items.next-code'))->json('data.code'))->toBe(5011);
});

it('handles counter progression correctly', function () {
    Setting::set('items', 'code_counter', 5000, 'number');

    $this->postJson(route('setups.items.store'), $this->itemPayload(['code' => '5000', 'short_name' => 'Counter Item']))->assertCreated();

    expect((int) $this->getJson(route('setups.items.next-code'))->json('data.code'))->toBe(5001);
});
