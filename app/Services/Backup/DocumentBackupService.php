<?php

namespace App\Services\Backup;

use App\Models\Document;
use App\Models\DocumentBackup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentBackupService
{
    public function __construct(protected BackupStorageService $storageService) {}

    /**
     * Incrementally push every not-yet-successfully-backed-up document file to one
     * remote disk. Reads from the `public` disk (files already live there — no local
     * copy is made). The `document_backups` ledger is the single source of truth:
     * one row per (document, disk) recording success or failure. Failed rows are
     * retried on the next run and pruned after the configured retention.
     *
     * Returns a live summary (not persisted).
     *
     * @return array{disk:string,status:string,files_copied:int,files_skipped:int,files_failed:int,total_bytes:int,duration_seconds:int,error_message:string|null}
     */
    public function run(Tenant $tenant, string $disk): array
    {
        $start   = microtime(true);
        $copied  = 0;
        $skipped = 0;
        $failed  = 0;
        $bytes   = 0;

        try {
            $remote = $this->storageService->buildDisk($tenant, $disk);
        } catch (\Throwable $e) {
            // Run-level failure (e.g. bad credentials / unreachable host): nothing was attempted.
            Log::error('Document backup could not open remote disk', [
                'tenant' => $tenant->tenant_key,
                'disk'   => $disk,
                'error'  => $e->getMessage(),
            ]);

            return $this->summary($disk, 'failed', 0, 0, 0, 0, $start, $e->getMessage());
        }

        $public = Storage::disk('public');

        // lazyById (not cursor): we write ledger rows on the same tenant connection during
        // iteration — an unbuffered cursor would error, and offset paging would skip rows
        // as the "not yet succeeded" match set shrinks. Id-based paging is safe on both.
        $this->pendingDocuments($disk)->lazyById()->each(function (Document $doc) use ($remote, $public, $disk, &$copied, &$skipped, &$failed, &$bytes) {
            if (!$public->exists($doc->file_path)) {
                // Physical file legitimately gone — not a backup failure, just nothing to copy.
                $skipped++;
                return;
            }

            $backup = DocumentBackup::firstOrNew(['document_id' => $doc->id, 'disk' => $disk]);
            $backup->file_path = $doc->file_path;
            // Carry the document's own upload year/month/module (authoritative), not "now".
            $backup->year      = $doc->year;
            $backup->month     = $doc->month;
            $backup->module    = $doc->module;
            $backup->attempts  = (int) $backup->attempts + 1;

            try {
                $stream = $public->readStream($doc->file_path);
                $remote->writeStream($doc->file_path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                $backup->status       = DocumentBackup::STATUS_SUCCESS;
                $backup->file_size    = (int) $doc->file_size;
                $backup->backed_up_at = now();
                $backup->last_error   = null;
                $backup->save();

                $copied++;
                $bytes += (int) $doc->file_size;
            } catch (\Throwable $e) {
                $backup->status     = DocumentBackup::STATUS_FAILED;
                $backup->last_error = $e->getMessage();
                $backup->save();

                $failed++;
            }
        });

        return $this->summary($disk, 'success', $copied, $skipped, $failed, $bytes, $start, null);
    }

    /**
     * Documents (including soft-deleted — files persist) not yet SUCCESSFULLY backed up
     * to this disk. Includes never-tried documents and previously-failed ones (retry).
     * Kept-forever policy: deletes never remove remote copies.
     *
     * @return Builder<Document>
     */
    public function pendingDocuments(string $disk): Builder
    {
        return Document::withTrashed()
            ->whereNotExists(function ($q) use ($disk) {
                $q->select(DB::raw(1))
                  ->from('document_backups')
                  ->whereColumn('document_backups.document_id', 'documents.id')
                  ->where('document_backups.disk', $disk)
                  ->where('document_backups.status', DocumentBackup::STATUS_SUCCESS);
            });
    }

    /**
     * @return array{disk:string,status:string,files_copied:int,files_skipped:int,files_failed:int,total_bytes:int,duration_seconds:int,error_message:string|null}
     */
    private function summary(string $disk, string $status, int $copied, int $skipped, int $failed, int $bytes, float $start, ?string $error): array
    {
        return [
            'disk'             => $disk,
            'status'           => $status,
            'files_copied'     => $copied,
            'files_skipped'    => $skipped,
            'files_failed'     => $failed,
            'total_bytes'      => $bytes,
            'duration_seconds' => (int) round(microtime(true) - $start),
            'error_message'    => $error,
        ];
    }
}
