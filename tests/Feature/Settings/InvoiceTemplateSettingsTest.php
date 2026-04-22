<?php

use App\Models\User;

uses()->group('api', 'settings', 'invoice-template');

beforeEach(function () {
    $this->user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($this->user, 'sanctum');
    // Seed defaults so index returns them
    $this->postJson(route('settings.invoice.reset'));
});

describe('Invoice Template Settings', function () {

    it('returns template and language in invoice settings index', function () {
        $response = $this->getJson(route('settings.invoice.index'));

        $response->assertOk()
            ->assertJsonPath('data.template', 'template-1')
            ->assertJsonPath('data.language', 'en');
    });

    it('can update the invoice template setting', function () {
        $response = $this->putJson(route('settings.invoice.update'), [
            'template' => 'template-2',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.template', 'template-2');
    });

    it('can update the invoice language setting', function () {
        $response = $this->putJson(route('settings.invoice.update'), [
            'language' => 'fr',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.language', 'fr');
    });

    it('rejects invalid template values', function () {
        $response = $this->putJson(route('settings.invoice.update'), [
            'template' => 'invalid-template',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['template']);
    });

    it('rejects invalid language values', function () {
        $response = $this->putJson(route('settings.invoice.update'), [
            'language' => 'xx',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    });

    it('reset returns template and language to defaults', function () {
        $this->putJson(route('settings.invoice.update'), [
            'template' => 'template-2',
            'language' => 'fr',
        ]);

        $response = $this->postJson(route('settings.invoice.reset'));

        $response->assertOk()
            ->assertJsonPath('data.template', 'template-1')
            ->assertJsonPath('data.language', 'en');
    });
});
