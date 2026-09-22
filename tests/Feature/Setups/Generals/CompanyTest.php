<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses()->group('api', 'setup', 'setup.company', 'company');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    Setting::where('group_name', 'company_details')->delete();

    Storage::fake('public');
});

describe('Company Details Management', function () {

    test('authed get returns full company details including identity', function () {
        Setting::create(['group_name' => 'company_details', 'key_name' => 'company_name', 'value' => 'Test Company', 'data_type' => 'string']);
        Setting::create(['group_name' => 'company_details', 'key_name' => 'tax_number', 'value' => 'TAX123', 'data_type' => 'string']);

        $response = $this->getJson(route('setups.company-details.get'));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Company Details',
                'data' => [
                    'company_name' => 'Test Company',
                    'tax_number'   => 'TAX123',
                ],
            ]);
    });

    test('can set company details text data', function () {
        $payload = [
            'company_name'  => 'GlobalZ Solutions',
            'address'       => '123 Business Street',
            'contact_phone' => '+1234567890',
            'contact_email' => 'info@globalz.com',
            'website'       => 'https://globalz.com',
            'tax_number'    => 'TAX123456789',
        ];

        $response = $this->postJson(route('setups.company-details.set'), $payload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Company details updated successfully']);

        foreach ($payload as $key => $value) {
            $setting = Setting::where('group_name', 'company_details')->where('key_name', $key)->first();
            expect($setting)->not->toBeNull();
            expect($setting->value)->toBe($value);
        }
    });

    test('can upload company logo into company_details group', function () {
        $logo = UploadedFile::fake()->image('logo.png', 100, 100);

        $response = $this->postJson(route('setups.company-details.set'), [
            'company_name' => 'Test Company',
            'logo'         => $logo,
        ]);

        $response->assertStatus(200);

        $logoSetting = Setting::where('group_name', 'company_details')->where('key_name', 'logo')->first();
        expect($logoSetting)->not->toBeNull();
        expect($logoSetting->value)->toContain('logo');
    });

    test('validates logo file upload', function () {
        $response = $this->postJson(route('setups.company-details.set'), [
            'logo' => UploadedFile::fake()->create('document.pdf', 1000),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['logo']);
    });

    test('validates email and website format', function () {
        $response = $this->postJson(route('setups.company-details.set'), [
            'contact_email' => 'invalid-email',
            'website'       => 'not-a-url',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['contact_email', 'website']);
    });

    test('handles partial updates correctly', function () {
        Setting::create(['group_name' => 'company_details', 'key_name' => 'company_name', 'value' => 'Original', 'data_type' => 'string']);
        Setting::create(['group_name' => 'company_details', 'key_name' => 'contact_email', 'value' => 'original@company.com', 'data_type' => 'string']);

        $this->postJson(route('setups.company-details.set'), ['company_name' => 'Updated'])->assertStatus(200);

        expect(Setting::where('group_name', 'company_details')->where('key_name', 'company_name')->first()->value)->toBe('Updated');
        expect(Setting::where('group_name', 'company_details')->where('key_name', 'contact_email')->first()->value)->toBe('original@company.com');
    });
});

describe('Public branding endpoint', function () {

    test('public endpoint hides company identity fields', function () {
        Setting::create(['group_name' => 'company_details', 'key_name' => 'company_name', 'value' => 'Public Co', 'data_type' => 'string']);
        Setting::create(['group_name' => 'company_details', 'key_name' => 'tax_number', 'value' => 'SECRET-TAX', 'data_type' => 'string']);
        Setting::create(['group_name' => 'company_details', 'key_name' => 'address', 'value' => 'Secret Address', 'data_type' => 'string']);
        Setting::create(['group_name' => 'company_details', 'key_name' => 'website', 'value' => 'https://secret.example', 'data_type' => 'string']);

        $response = $this->getJson(route('company-details.public'));

        $response->assertStatus(200)
            ->assertJson(['data' => ['company_name' => 'Public Co']]);

        $data = $response->json('data');
        expect($data)->not->toHaveKey('tax_number');
        expect($data)->not->toHaveKey('address');
        expect($data)->not->toHaveKey('website');
    });
});
