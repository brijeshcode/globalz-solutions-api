<?php

use App\Models\Document;
use App\Models\User;

uses()->group('api', 'documents');

function makeDocRow(array $overrides = []): Document
{
    return Document::create(array_merge([
        'documentable_type' => 'App\\Models\\Customers\\Customer',
        'documentable_id'   => 1,
        'original_name'     => 'file.pdf',
        'file_name'         => 'file.pdf',
        'file_path'         => 'documents/test/2026/07/customers/file.pdf',
        'file_size'         => 10,
        'mime_type'         => 'application/pdf',
        'file_extension'    => 'pdf',
    ], $overrides));
}

it('extracts year, month and module from a stored path', function () {
    expect(Document::pathParts('documents/live/2026/07/expensetransactions/expensetransaction-173-06-07-2026-12-25-24-Noria.pdf'))
        ->toBe(['year' => 2026, 'month' => 7, 'module' => 'expensetransactions']);

    // Landlord fallback path (no tenant segment) still resolves from the last 4 segments.
    expect(Document::pathParts('documents/2025/12/customers/customer-1-file.pdf'))
        ->toBe(['year' => 2025, 'month' => 12, 'module' => 'customers']);

    // Out-of-range month / non-numeric year fall back to null.
    expect(Document::pathParts('documents/live/abcd/13/customers/x.pdf'))
        ->toBe(['year' => null, 'month' => null, 'module' => 'customers']);
});

it('filters documents by year, month and module_name', function () {
    $this->actingAs(User::factory()->create(), 'sanctum');

    makeDocRow([
        'original_name' => 'expense.pdf',
        'file_path'     => 'documents/test/2026/07/expensetransactions/expense.pdf',
        'year'          => 2026,
        'month'         => 7,
        'module'        => 'expensetransactions',
    ]);
    makeDocRow([
        'original_name' => 'customer.pdf',
        'file_path'     => 'documents/test/2025/03/customers/customer.pdf',
        'year'          => 2025,
        'month'         => 3,
        'module'        => 'customers',
    ]);

    $this->getJson(route('documents.index', ['year' => 2026]))
        ->assertOk()
        ->assertJsonFragment(['original_name' => 'expense.pdf'])
        ->assertJsonMissing(['original_name' => 'customer.pdf']);

    $this->getJson(route('documents.index', ['module_name' => 'customers']))
        ->assertOk()
        ->assertJsonFragment(['original_name' => 'customer.pdf'])
        ->assertJsonMissing(['original_name' => 'expense.pdf']);

    $this->getJson(route('documents.index', ['month' => 7]))
        ->assertOk()
        ->assertJsonFragment(['original_name' => 'expense.pdf'])
        ->assertJsonMissing(['original_name' => 'customer.pdf']);
});
