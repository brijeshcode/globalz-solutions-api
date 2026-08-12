<?php

use App\Models\DocumentBackup;
use Illuminate\Support\Facades\DB;

uses()->group('api', 'backup', 'document-backup');

it('records a document backup ledger row on the tenant connection', function () {
    $row = DocumentBackup::create([
        'document_id'  => 1,
        'disk'         => 'ftp',
        'file_path'    => 'documents/test/2026/08/customers/a.pdf',
        'file_size'    => 123,
        'backed_up_at' => now(),
    ]);

    expect($row->getConnection()->getName())->toBe('tenant');
    expect(DB::connection('tenant')->table('document_backups')->where('id', $row->id)->exists())->toBeTrue();
    expect(DocumentBackup::where('document_id', 1)->where('disk', 'ftp')->exists())->toBeTrue();
});
