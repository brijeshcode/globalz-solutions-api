<?php

use App\Helpers\FeatureHelper;
use App\Models\MirrorLog;
use App\Models\Setting;
use Tests\Feature\Mirror\Concerns\HasMirrorSetup;

uses(HasMirrorSetup::class);

beforeEach(function () {
    $this->setUpMirror();
});

it('returns last 25 mirror logs by default', function () {
    MirrorLog::factory()->count(30)->create();

    $this->getJson(route('mirrors.logs'))
        ->assertOk()
        ->assertJsonCount(25, 'data');
});

it('respects custom display_limit setting', function () {
    Setting::set('mirror', 'display_limit', '10', Setting::TYPE_NUMBER);
    MirrorLog::factory()->count(20)->create();

    $this->getJson(route('mirrors.logs'))
        ->assertOk()
        ->assertJsonCount(10, 'data');
});

it('returns logs ordered by started_at descending', function () {
    MirrorLog::factory()->create(['started_at' => now()->subHours(2)]);
    MirrorLog::factory()->create(['started_at' => now()->subHour()]);
    MirrorLog::factory()->create(['started_at' => now()]);

    $response = $this->getJson(route('mirrors.logs'))->assertOk();

    $logs       = $response->json('data');
    $timestamps = array_column($logs, 'started_at');

    expect($timestamps[0])->toBeGreaterThanOrEqual($timestamps[1]);
    expect($timestamps[1])->toBeGreaterThanOrEqual($timestamps[2]);
});

it('returns empty array when no logs exist', function () {
    $this->getJson(route('mirrors.logs'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns 403 if feature not enabled', function () {
    $this->setUpMirror(featureEnabled: false);

    $this->getJson(route('mirrors.logs'))
        ->assertForbidden();
});
