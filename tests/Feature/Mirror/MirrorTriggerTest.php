<?php

use App\Jobs\MirrorTenantJob;
use Tests\Feature\Mirror\Concerns\HasMirrorSetup;

uses(HasMirrorSetup::class);

beforeEach(function () {
    $this->setUpMirror();
});

it('dispatches a mirror job and returns queued message', function () {
    Queue::fake();

    $this->postJson(route('mirrors.trigger'))
        ->assertOk()
        ->assertJsonFragment(['message' => 'Mirror job queued — check logs for progress.']);

    Queue::assertPushed(MirrorTenantJob::class);
});

it('returns 403 if feature not enabled', function () {
    $this->setUpMirror(featureEnabled: false);

    $this->postJson(route('mirrors.trigger'))
        ->assertForbidden();
});
