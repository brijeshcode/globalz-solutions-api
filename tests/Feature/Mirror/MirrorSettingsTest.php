<?php

use App\Helpers\FeatureHelper;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mirror\DatabaseMirrorService;
use Tests\Feature\Mirror\Concerns\HasMirrorSetup;

uses(HasMirrorSetup::class);

beforeEach(function () {
    $this->setUpMirror();
});

// ── FeatureHelper ──────────────────────────────────────────────────────────

it('isDatabaseMirror returns true when feature is enabled', function () {
    expect(FeatureHelper::isDatabaseMirror())->toBeTrue();
});

it('isDatabaseMirror returns false when feature is disabled', function () {
    $this->setUpMirror(featureEnabled: false);
    expect(FeatureHelper::isDatabaseMirror())->toBeFalse();
});

// ── Host validation ────────────────────────────────────────────────────────

it('rejects private IP 192.168.x.x', function () {
    $service = new DatabaseMirrorService();
    expect(fn() => $service->validateHost('192.168.1.1'))
        ->toThrow(\InvalidArgumentException::class, 'Private or internal IP addresses are not allowed');
});

it('rejects private IP 10.x.x.x', function () {
    $service = new DatabaseMirrorService();
    expect(fn() => $service->validateHost('10.0.0.1'))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejects loopback 127.0.0.1', function () {
    $service = new DatabaseMirrorService();
    expect(fn() => $service->validateHost('127.0.0.1'))
        ->toThrow(\InvalidArgumentException::class);
});

it('accepts a valid public hostname', function () {
    $service = new DatabaseMirrorService();
    expect(fn() => $service->validateHost('db.example.com'))->not()->toThrow(\InvalidArgumentException::class);
});

// ── GET /mirrors/settings ──────────────────────────────────────────────────

it('returns mirror settings without password', function () {
    Setting::set('mirror', 'host', 'db.example.com');
    Setting::set('mirror', 'port', '3306', Setting::TYPE_NUMBER);
    Setting::set('mirror', 'enabled', '1', Setting::TYPE_BOOLEAN);

    $this->getJson(route('mirrors.settings'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['enabled', 'db_type', 'host', 'port', 'database', 'username', 'store_limit', 'display_limit']])
        ->assertJsonMissing(['password']);
});

it('returns 403 if feature is not enabled', function () {
    $this->setUpMirror(featureEnabled: false);

    $this->getJson(route('mirrors.settings'))
        ->assertForbidden();
});

it('returns 403 if not superadmin', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($user, 'sanctum');

    $this->getJson(route('mirrors.settings'))
        ->assertForbidden();
});

// ── PUT /mirrors/settings ──────────────────────────────────────────────────

it('saves mirror settings and encrypts password', function () {
    $this->putJson(route('mirrors.settings.update'), [
        'enabled'  => true,
        'db_type'  => 'mysql',
        'host'     => 'db.example.com',
        'port'     => 3306,
        'database' => 'remote_db',
        'username' => 'mirror_user',
        'password' => 'secret123',
    ])->assertOk();

    expect(Setting::get('mirror', 'host'))->toBe('db.example.com');
    expect(Setting::get('mirror', 'enabled', false, false, Setting::TYPE_BOOLEAN))->toBeTrue();

    $passwordSetting = Setting::where('group_name', 'mirror')->where('key_name', 'password')->first();
    expect($passwordSetting->is_encrypted)->toBeTrue();
});

it('rejects private IP host on settings save', function () {
    $this->putJson(route('mirrors.settings.update'), [
        'host' => '192.168.1.1',
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['host']);
});

it('does not overwrite password if not sent', function () {
    $setting = Setting::firstOrNew(
        ['group_name' => 'mirror', 'key_name' => 'password'],
        ['data_type' => Setting::TYPE_STRING, 'is_encrypted' => true]
    );
    $setting->is_encrypted = true;
    $setting->data_type    = Setting::TYPE_STRING;
    $setting->value        = 'original_password';
    $setting->save();

    $original = Setting::where('group_name', 'mirror')->where('key_name', 'password')->first()->value;

    $this->putJson(route('mirrors.settings.update'), ['host' => 'db2.example.com'])
        ->assertOk();

    $after = Setting::where('group_name', 'mirror')->where('key_name', 'password')->first()->value;
    expect($after)->toBe($original);
});

it('can update store_limit and display_limit', function () {
    $this->putJson(route('mirrors.settings.update'), [
        'store_limit'   => 500,
        'display_limit' => 10,
    ])->assertOk();

    expect((int) Setting::get('mirror', 'store_limit', 1000, false, Setting::TYPE_NUMBER))->toBe(500);
    expect((int) Setting::get('mirror', 'display_limit', 25, false, Setting::TYPE_NUMBER))->toBe(10);
});
