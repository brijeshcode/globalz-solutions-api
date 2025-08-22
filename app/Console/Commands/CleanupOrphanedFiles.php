<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:cleanup-orphaned
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--days=30 : Delete soft-deleted documents older than X days}
                            {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned document files and old soft-deleted documents';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $days = (int) $this->option('days');
        $force = $this->option('force');

        $this->info('Starting document cleanup process...');
        $this->newLine();

        // Step 1: Find orphaned files (files without database records)
        $this->info('🔍 Scanning for orphaned files...');
        $orphanedFiles = $this->findOrphanedFiles();
        
        if (count($orphanedFiles) > 0) {
            $this->warn("Found {count($orphanedFiles)} orphaned files:");
            
            foreach ($orphanedFiles as $file) {
                $this->line("  - {$file}");
            }
            
            if (!$dryRun) {
                if ($force || $this->confirm('Delete these orphaned files?')) {
                    $deletedCount = $this->deleteOrphanedFiles($orphanedFiles);
                    $this->info("✅ Deleted {$deletedCount} orphaned files");
                } else {
                    $this->info('⏭️  Skipping orphaned files cleanup');
                }
            } else {
                $this->info('🔍 DRY RUN: Would delete ' . count($orphanedFiles) . ' orphaned files');
            }
        } else {
            $this->info('✅ No orphaned files found');
        }

        $this->newLine();

        // Step 2: Find old soft-deleted documents
        $this->info("🔍 Scanning for soft-deleted documents older than {$days} days...");
        $oldDeletedDocuments = $this->findOldSoftDeletedDocuments($days);
        
        if (count($oldDeletedDocuments) > 0) {
            $this->warn("Found " . count($oldDeletedDocuments) . " old soft-deleted documents:");
            
            foreach ($oldDeletedDocuments as $document) {
                $deletedAt = $document->deleted_at->format('Y-m-d H:i:s');
                $this->line("  - ID: {$document->id} | {$document->original_name} | Deleted: {$deletedAt}");
            }
            
            if (!$dryRun) {
                if ($force || $this->confirm("Permanently delete these documents and their files?")) {
                    $deletedCount = $this->deleteOldSoftDeletedDocuments($oldDeletedDocuments);
                    $this->info("✅ Permanently deleted {$deletedCount} old documents");
                } else {
                    $this->info('⏭️  Skipping old documents cleanup');
                }
            } else {
                $this->info('🔍 DRY RUN: Would permanently delete ' . count($oldDeletedDocuments) . ' old documents');
            }
        } else {
            $this->info("✅ No soft-deleted documents older than {$days} days found");
        }

        $this->newLine();

        // Step 3: Find database records without files
        $this->info('🔍 Scanning for database records without files...');
        $recordsWithoutFiles = $this->findRecordsWithoutFiles();
        
        if (count($recordsWithoutFiles) > 0) {
            $this->warn("Found " . count($recordsWithoutFiles) . " database records without files:");
            
            foreach ($recordsWithoutFiles as $document) {
                $this->line("  - ID: {$document->id} | {$document->original_name} | Path: {$document->file_path}");
            }
            
            if (!$dryRun) {
                if ($force || $this->confirm('Mark these documents as having missing files (add metadata flag)?')) {
                    $markedCount = $this->markRecordsWithMissingFiles($recordsWithoutFiles);
                    $this->info("✅ Marked {$markedCount} records as having missing files");
                } else {
                    $this->info('⏭️  Skipping missing files marking');
                }
            } else {
                $this->info('🔍 DRY RUN: Would mark ' . count($recordsWithoutFiles) . ' records as having missing files');
            }
        } else {
            $this->info('✅ All database records have corresponding files');
        }

        $this->newLine();

        // Summary
        $this->info('📊 Cleanup Summary:');
        $this->info("   Orphaned files: " . count($orphanedFiles));
        $this->info("   Old soft-deleted documents: " . count($oldDeletedDocuments));
        $this->info("   Records without files: " . count($recordsWithoutFiles));
        
        if ($dryRun) {
            $this->newLine();
            $this->info('🔍 This was a DRY RUN - no changes were made');
            $this->info('Run without --dry-run to perform actual cleanup');
        }

        $this->info('✅ Document cleanup completed');
        
        return Command::SUCCESS;
    }

    /**
     * Find files in storage that don't have corresponding database records.
     */
    private function findOrphanedFiles(): array
    {
        $orphanedFiles = [];
        $disk = Storage::disk('public');
        
        // Get all document file paths from database
        $databasePaths = Document::withTrashed()
            ->pluck('file_path')
            ->toArray();
        
        // Get all files in document directories
        $documentDirs = ['supplier/documents', 'warehouse/documents', 'user/documents', 'general/documents'];
        
        foreach ($documentDirs as $dir) {
            if ($disk->exists($dir)) {
                $files = $disk->allFiles($dir);
                
                foreach ($files as $file) {
                    if (!in_array($file, $databasePaths)) {
                        $orphanedFiles[] = $file;
                    }
                }
            }
        }
        
        return $orphanedFiles;
    }

    /**
     * Delete orphaned files from storage.
     */
    private function deleteOrphanedFiles(array $files): int
    {
        $deletedCount = 0;
        $disk = Storage::disk('public');
        
        foreach ($files as $file) {
            try {
                if ($disk->delete($file)) {
                    $deletedCount++;
                }
            } catch (\Exception $e) {
                $this->error("Failed to delete {$file}: " . $e->getMessage());
            }
        }
        
        return $deletedCount;
    }

    /**
     * Find soft-deleted documents older than specified days.
     */
    private function findOldSoftDeletedDocuments(int $days): \Illuminate\Database\Eloquent\Collection
    {
        return Document::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($days))
            ->get();
    }

    /**
     * Permanently delete old soft-deleted documents and their files.
     */
    private function deleteOldSoftDeletedDocuments(\Illuminate\Database\Eloquent\Collection $documents): int
    {
        $deletedCount = 0;
        $disk = Storage::disk('public');
        
        foreach ($documents as $document) {
            try {
                // Delete physical file if it exists
                if ($disk->exists($document->file_path)) {
                    $disk->delete($document->file_path);
                }
                
                // Permanently delete from database
                $document->forceDelete();
                $deletedCount++;
                
            } catch (\Exception $e) {
                $this->error("Failed to delete document ID {$document->id}: " . $e->getMessage());
            }
        }
        
        return $deletedCount;
    }

    /**
     * Find database records that don't have corresponding files.
     */
    private function findRecordsWithoutFiles(): \Illuminate\Database\Eloquent\Collection
    {
        $documentsWithoutFiles = collect();
        $disk = Storage::disk('public');
        
        // Check active documents
        Document::chunk(100, function ($documents) use ($disk, $documentsWithoutFiles) {
            foreach ($documents as $document) {
                if (!$disk->exists($document->file_path)) {
                    $documentsWithoutFiles->push($document);
                }
            }
        });
        
        return $documentsWithoutFiles;
    }

    /**
     * Mark database records as having missing files.
     */
    private function markRecordsWithMissingFiles(\Illuminate\Database\Eloquent\Collection $documents): int
    {
        $markedCount = 0;
        
        foreach ($documents as $document) {
            try {
                $metadata = $document->metadata ?? [];
                $metadata['file_missing'] = true;
                $metadata['file_missing_detected_at'] = now()->toISOString();
                
                $document->update(['metadata' => $metadata]);
                $markedCount++;
                
            } catch (\Exception $e) {
                $this->error("Failed to mark document ID {$document->id}: " . $e->getMessage());
            }
        }
        
        return $markedCount;
    }

    /**
     * Get storage usage statistics.
     */
    private function getStorageStats(): array
    {
        $disk = Storage::disk('public');
        $totalSize = 0;
        $fileCount = 0;
        
        $documentDirs = ['supplier/documents', 'warehouse/documents', 'user/documents', 'general/documents'];
        
        foreach ($documentDirs as $dir) {
            if ($disk->exists($dir)) {
                $files = $disk->allFiles($dir);
                $fileCount += count($files);
                
                foreach ($files as $file) {
                    $totalSize += $disk->size($file);
                }
            }
        }
        
        return [
            'total_files' => $fileCount,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize)
        ];
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}