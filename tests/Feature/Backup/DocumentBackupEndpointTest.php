<?php

use App\Models\Document;
use App\Models\DocumentBackup;
use App\Models\Setting;
use App\Models\User;

uses()->group('api', 'backup', 'document-backup');

beforeEach(function () {
    // Backup endpoints require super_admin (BackupController constructor → RoleHelper::canSuperAdmin).
    $this->user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($this->user, 'sanctum');
});

it('rejects the document backup trigger when no remote drivers are configured', function () {
    // Default storage_drivers is ['local'] → no remote destinations.
    $this->postJson(route('backups.documents.trigger'))
        ->assertStatus(400);
});

it('reports per-disk document backup status from the ledger', function () {
    Setting::set('backup', 'storage_drivers', json_encode(['local', 'ftp']), Setting::TYPE_JSON);

    $doc = Document::create([
        'documentable_type' => 'App\\Models\\Customers\\Customer',
        'documentable_id'   => 1,
        'original_name'     => 'a.pdf',
        'file_name'         => 'a.pdf',
        'file_path'         => 'documents/test/2026/08/customers/a.pdf',
        'file_size'         => 10,
        'mime_type'         => 'application/pdf',
        'file_extension'    => 'pdf',
    ]);

    DocumentBackup::create([
        'document_id'  => $doc->id,
        'disk'         => 'ftp',
        'status'       => DocumentBackup::STATUS_SUCCESS,
        'file_path'    => $doc->file_path,
        'file_size'    => 10,
        'backed_up_at' => now(),
    ]);

    $this->getJson(route('backups.documents.status'))
        ->assertOk()
        ->assertJsonFragment([
            'disk'            => 'ftp',
            'documents_total' => 1,
            'backed_up'       => 1,
            'failed'          => 0,
            'pending'         => 0,
        ]);
});
