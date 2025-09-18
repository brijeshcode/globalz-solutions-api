<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
uses()->group('api', 'setup', 'setup.company', 'company');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    // Clear existing company settings
    Setting::where('group_name', 'company')->delete();

    // Setup storage for testing
    Storage::fake('public');
});

describe('Company Data Management', function () {

    test('can get all company data', function () {
        // Create some test company settings
        Setting::create([
            'group_name' => 'company',
            'key_name' => 'name',
            'value' => 'Test Company',
            'data_type' => 'string'
        ]);

        Setting::create([
            'group_name' => 'company',
            'key_name' => 'email',
            'value' => 'test@company.com',
            'data_type' => 'string'
        ]);

        $response = $this->getJson(route('setups.company.get'));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data'
                ])
                ->assertJson([
                    'message' => 'Company data',
                    'data' => [
                        'name' => 'Test Company',
                        'email' => 'test@company.com'
                    ]
                ]);
    });

    test('can set company text data', function () {
        $companyData = [
            'name' => 'GlobalZ Solutions',
            'address' => '123 Business Street',
            'phone' => '+1234567890',
            'email' => 'info@globalz.com',
            'website' => 'https://globalz.com',
            'tax_number' => 'TAX123456789'
        ];

        $response = $this->postJson(route('setups.company.set'), $companyData);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Company data updated successfully'
                ]);

        // Verify data was saved to settings
        foreach ($companyData as $key => $value) {
            $setting = Setting::where('group_name', 'company')
                            ->where('key_name', $key)
                            ->first();
            expect($setting)->not->toBeNull();
            expect($setting->value)->toBe($value);
        }
    });

    test('can upload company logo', function () {
        $logoFile = UploadedFile::fake()->image('logo.png', 100, 100);

        $response = $this->postJson(route('setups.company.set'), [
            'name' => 'Test Company',
            'logo' => $logoFile
        ]);

        $response->assertStatus(200);

        // Verify logo file path was saved
        $logoSetting = Setting::where('group_name', 'company')
                            ->where('key_name', 'logo')
                            ->first();

        expect($logoSetting)->not->toBeNull();
        expect($logoSetting->value)->toContain('documents/');
        expect($logoSetting->value)->toContain('company/');
        expect($logoSetting->value)->toContain('logo');
    });

    test('can upload company stamp', function () {
        $stampFile = UploadedFile::fake()->image('stamp.jpg', 80, 80);

        $response = $this->postJson(route('setups.company.set'), [
            'name' => 'Test Company',
            'stamp' => $stampFile
        ]);

        $response->assertStatus(200);

        // Verify stamp file path was saved
        $stampSetting = Setting::where('group_name', 'company')
                             ->where('key_name', 'stamp')
                             ->first();

        expect($stampSetting)->not->toBeNull();
        expect($stampSetting->value)->toContain('documents/');
        expect($stampSetting->value)->toContain('company/');
        expect($stampSetting->value)->toContain('stamp');
    });

    test('can upload both logo and stamp together', function () {
        $logoFile = UploadedFile::fake()->image('logo.png', 100, 100);
        $stampFile = UploadedFile::fake()->image('stamp.jpg', 80, 80);

        $response = $this->postJson(route('setups.company.set'), [
            'name' => 'Test Company',
            'logo' => $logoFile,
            'stamp' => $stampFile
        ]);

        $response->assertStatus(200);

        // Verify both files were saved
        $logoSetting = Setting::where('group_name', 'company')
                            ->where('key_name', 'logo')
                            ->first();
        $stampSetting = Setting::where('group_name', 'company')
                             ->where('key_name', 'stamp')
                             ->first();

        expect($logoSetting)->not->toBeNull();
        expect($stampSetting)->not->toBeNull();
    });

    test('validates logo file upload', function () {
        $invalidFile = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->postJson(route('setups.company.set'), [
            'logo' => $invalidFile
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['logo']);
    });

    test('validates stamp file upload', function () {
        $invalidFile = UploadedFile::fake()->create('document.txt', 1000);

        $response = $this->postJson(route('setups.company.set'), [
            'stamp' => $invalidFile
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['stamp']);
    });

    test('validates file size limits', function () {
        $largeLogo = UploadedFile::fake()->image('large_logo.png', 2000, 2000)->size(3000); // 3MB

        $response = $this->postJson(route('setups.company.set'), [
            'logo' => $largeLogo
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['logo']);
    });

    test('can get selected company fields', function () {
        // Create test data
        Setting::create([
            'group_name' => 'company',
            'key_name' => 'name',
            'value' => 'Test Company',
            'data_type' => 'string'
        ]);

        Setting::create([
            'group_name' => 'company',
            'key_name' => 'email',
            'value' => 'test@company.com',
            'data_type' => 'string'
        ]);

        Setting::create([
            'group_name' => 'company',
            'key_name' => 'logo',
            'value' => 'documents/2025/01/company/logo.png',
            'data_type' => 'string'
        ]);

        $response = $this->postJson(route('setups.company.getSelected'), [
            'fields' => ['name', 'email', 'logo']
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'name',
                        'email',
                        'logo' => [
                            'file_path',
                            'url'
                        ]
                    ]
                ])
                ->assertJson([
                    'data' => [
                        'name' => 'Test Company',
                        'email' => 'test@company.com'
                    ]
                ]);

        // Verify logo returns structured data with URL
        expect($response->json('data.logo.file_path'))->toBe('documents/2025/01/company/logo.png');
        expect($response->json('data.logo.url'))->toContain('storage/documents/2025/01/company/logo.png');
    });

    test('validates selected fields request', function () {
        $response = $this->postJson(route('setups.company.getSelected'), [
            'fields' => []
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['fields']);
    });

    test('validates invalid field names in selected fields', function () {
        $response = $this->postJson(route('setups.company.getSelected'), [
            'fields' => ['invalid_field', 'name']
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['fields.0']);
    });

    test('validates email format', function () {
        $response = $this->postJson(route('setups.company.set'), [
            'email' => 'invalid-email'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    });

    test('validates website URL format', function () {
        $response = $this->postJson(route('setups.company.set'), [
            'website' => 'not-a-url'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['website']);
    });

    test('validates string length limits', function () {
        $response = $this->postJson(route('setups.company.set'), [
            'name' => str_repeat('a', 256), // Over 255 limit
            'address' => str_repeat('b', 501), // Over 500 limit
            'phone' => str_repeat('1', 51) // Over 50 limit
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'name',
                    'address',
                    'phone'
                ]);
    });

    test('handles partial updates correctly', function () {
        // Set initial data
        Setting::create([
            'group_name' => 'company',
            'key_name' => 'name',
            'value' => 'Original Company',
            'data_type' => 'string'
        ]);

        Setting::create([
            'group_name' => 'company',
            'key_name' => 'email',
            'value' => 'original@company.com',
            'data_type' => 'string'
        ]);

        // Update only name
        $response = $this->postJson(route('setups.company.set'), [
            'name' => 'Updated Company'
        ]);

        $response->assertStatus(200);

        // Verify only name was updated, email remains unchanged
        $nameSetting = Setting::where('group_name', 'company')
                            ->where('key_name', 'name')
                            ->first();
        $emailSetting = Setting::where('group_name', 'company')
                             ->where('key_name', 'email')
                             ->first();

        expect($nameSetting->value)->toBe('Updated Company');
        expect($emailSetting->value)->toBe('original@company.com');
    });

});
