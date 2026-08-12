<?php

use App\Models\Document;
use App\Models\DocumentBackup;
use App\Services\Backup\BackupStorageService;
use App\Services\Backup\DocumentBackupService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

uses()->group('api', 'backup', 'document-backup');

function makeDoc(string $path, int $size = 10): Document
{
    Storage::disk('public')->put($path, str_repeat('x', $size));

    // Real uploads store year/month/module alongside the file; mirror that here.
    $parts = Document::pathParts($path);

    return Document::create([
        'documentable_type' => 'App\\Models\\Customers\\Customer',
        'documentable_id'   => 1,
        'original_name'     => basename($path),
        'file_name'         => basename($path),
        'file_path'         => $path,
        'file_size'         => $size,
        'mime_type'         => 'application/pdf',
        'file_extension'    => 'pdf',
        'year'              => $parts['year'],
        'month'             => $parts['month'],
        'module'            => $parts['module'],
    ]);
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('remote');

    // buildDisk() would open a real ftp/s3 connection — return a faked local disk instead.
    $storage = Mockery::mock(BackupStorageService::class);
    $storage->shouldReceive('buildDisk')->andReturn(Storage::disk('remote'));

    $this->service = new DocumentBackupService($storage);
});

it('copies only pending documents and records success in the ledger', function () {
    $a = makeDoc('documents/test/2026/08/customers/a.pdf');
    $b = makeDoc('documents/test/2026/08/customers/b.pdf');

    $result = $this->service->run($this->tenant, 'ftp');

    expect($result['status'])->toBe('success');
    expect($result['files_copied'])->toBe(2);
    expect($result['files_skipped'])->toBe(0);
    expect($result['total_bytes'])->toBe(20);

    Storage::disk('remote')->assertExists($a->file_path);
    Storage::disk('remote')->assertExists($b->file_path);
    expect(DocumentBackup::where('disk', 'ftp')->where('status', DocumentBackup::STATUS_SUCCESS)->count())->toBe(2);

    // Ledger carries year/month/module denormalized from the document path.
    $row = DocumentBackup::where('document_id', $a->id)->first();
    expect($row->year)->toBe(2026);
    expect($row->month)->toBe(8);
    expect($row->module)->toBe('customers');
});

it('is incremental: a second run copies only newly added files', function () {
    makeDoc('documents/test/2026/08/customers/a.pdf');
    $this->service->run($this->tenant, 'ftp');

    makeDoc('documents/test/2026/08/customers/c.pdf');
    $result = $this->service->run($this->tenant, 'ftp');

    expect($result['files_copied'])->toBe(1);
    expect(DocumentBackup::where('disk', 'ftp')->count())->toBe(2);
});

it('tracks each disk independently', function () {
    makeDoc('documents/test/2026/08/customers/a.pdf');

    $this->service->run($this->tenant, 'ftp');
    $result = $this->service->run($this->tenant, 's3');

    // s3 has never seen this file, so it must still be copied even though ftp has it.
    expect($result['files_copied'])->toBe(1);
    expect(DocumentBackup::where('disk', 's3')->count())->toBe(1);
    expect(DocumentBackup::where('disk', 'ftp')->count())->toBe(1);
});

it('skips documents whose physical file is missing without creating a ledger row', function () {
    $doc = Document::create([
        'documentable_type' => 'App\\Models\\Customers\\Customer',
        'documentable_id'   => 1,
        'original_name'     => 'gone.pdf',
        'file_name'         => 'gone.pdf',
        'file_path'         => 'documents/test/2026/08/customers/gone.pdf', // never written to disk
        'file_size'         => 10,
        'mime_type'         => 'application/pdf',
        'file_extension'    => 'pdf',
    ]);

    $result = $this->service->run($this->tenant, 'ftp');

    expect($result['files_skipped'])->toBe(1);
    expect($result['files_copied'])->toBe(0);
    expect(DocumentBackup::where('document_id', $doc->id)->exists())->toBeFalse();
});

it('records a failed ledger row with the error, then retries and succeeds on the next run', function () {
    $doc = makeDoc('documents/test/2026/08/customers/a.pdf');

    // First run: the remote write throws (e.g. connection dropped mid-file).
    $throwing = Mockery::mock(Filesystem::class);
    $throwing->shouldReceive('writeStream')->andThrow(new RuntimeException('connection reset'));
    $failingStorage = Mockery::mock(BackupStorageService::class);
    $failingStorage->shouldReceive('buildDisk')->andReturn($throwing);

    $failResult = (new DocumentBackupService($failingStorage))->run($this->tenant, 'ftp');

    expect($failResult['status'])->toBe('success'); // run-level ok, one file failed
    expect($failResult['files_failed'])->toBe(1);
    expect($failResult['files_copied'])->toBe(0);

    $row = DocumentBackup::where('document_id', $doc->id)->where('disk', 'ftp')->first();
    expect($row->status)->toBe(DocumentBackup::STATUS_FAILED);
    expect($row->attempts)->toBe(1);
    expect($row->last_error)->toContain('connection reset');
    expect($row->backed_up_at)->toBeNull();

    // Second run with a working remote retries the still-pending file.
    $okResult = $this->service->run($this->tenant, 'ftp');

    expect($okResult['files_copied'])->toBe(1);
    $row->refresh();
    expect($row->status)->toBe(DocumentBackup::STATUS_SUCCESS);
    expect($row->attempts)->toBe(2);
    expect($row->last_error)->toBeNull();
    Storage::disk('remote')->assertExists($doc->file_path);
});

it('returns a run-level failure when the remote disk cannot be opened', function () {
    makeDoc('documents/test/2026/08/customers/a.pdf');

    $badStorage = Mockery::mock(BackupStorageService::class);
    $badStorage->shouldReceive('buildDisk')->andThrow(new RuntimeException('bad credentials'));

    $result = (new DocumentBackupService($badStorage))->run($this->tenant, 'ftp');

    expect($result['status'])->toBe('failed');
    expect($result['error_message'])->toContain('bad credentials');
    expect($result['files_copied'])->toBe(0);
    expect(DocumentBackup::count())->toBe(0);
});
