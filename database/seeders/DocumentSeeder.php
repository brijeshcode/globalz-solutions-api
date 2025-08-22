<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Setups\Supplier;
use App\Models\Setups\Warehouse;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have some models to attach documents to
        $suppliers = Supplier::factory()->count(5)->create();
        $warehouses = Warehouse::factory()->count(3)->create();
        $users = User::factory()->count(3)->create();

        // Create sample documents for suppliers
        foreach ($suppliers as $supplier) {
            // Contract documents
            Document::factory()
                ->count(2)
                ->pdf()
                ->type('contract')
                ->folder('contracts')
                ->for($supplier, 'documentable')
                ->create();

            // Invoice documents
            Document::factory()
                ->count(3)
                ->pdf()
                ->type('invoice')
                ->folder('invoices')
                ->for($supplier, 'documentable')
                ->create();

            // Certificate images
            Document::factory()
                ->count(1)
                ->image()
                ->type('certificate')
                ->folder('certificates')
                ->featured()
                ->for($supplier, 'documentable')
                ->create();

            // Specification documents
            Document::factory()
                ->count(2)
                ->wordDocument()
                ->type('specification')
                ->folder('specifications')
                ->for($supplier, 'documentable')
                ->create();

            // Reports
            Document::factory()
                ->count(1)
                ->spreadsheet()
                ->type('report')
                ->folder('reports')
                ->public()
                ->for($supplier, 'documentable')
                ->create();
        }

        // Create sample documents for warehouses
        foreach ($warehouses as $warehouse) {
            // Inventory reports
            Document::factory()
                ->count(3)
                ->spreadsheet()
                ->type('report')
                ->folder('inventory')
                ->for($warehouse, 'documentable')
                ->create();

            // Photos
            Document::factory()
                ->count(5)
                ->image()
                ->type('photo')
                ->folder('photos')
                ->for($warehouse, 'documentable')
                ->create();

            // Manuals
            Document::factory()
                ->count(2)
                ->pdf()
                ->type('manual')
                ->folder('manuals')
                ->featured()
                ->for($warehouse, 'documentable')
                ->create();
        }

        // Create sample documents for users
        foreach ($users as $user) {
            // Personal documents
            Document::factory()
                ->count(2)
                ->pdf()
                ->type('personal')
                ->folder('personal')
                ->for($user, 'documentable')
                ->create();

            // Profile photos
            Document::factory()
                ->count(1)
                ->image()
                ->type('photo')
                ->folder('profile')
                ->public()
                ->for($user, 'documentable')
                ->create();
        }

        // Create some general documents (not attached to specific models)
        Document::factory()
            ->count(10)
            ->create([
                'documentable_type' => null,
                'documentable_id' => null,
                'folder' => 'general'
            ]);

        // Create some featured documents
        Document::factory()
            ->count(5)
            ->pdf()
            ->featured()
            ->public()
            ->create([
                'document_type' => 'announcement',
                'folder' => 'public',
                'title' => 'Important Company Announcement',
                'description' => 'This is an important announcement for all employees.'
            ]);

        // Create documents with various types for testing filters
        $documentTypes = [
            'contract', 'invoice', 'certificate', 'photo', 'manual',
            'specification', 'report', 'presentation', 'legal', 'warranty',
            'receipt', 'statement', 'agreement', 'proposal', 'quote'
        ];

        foreach ($documentTypes as $type) {
            Document::factory()
                ->count(2)
                ->type($type)
                ->create();
        }

        // Create some soft-deleted documents for testing restore functionality
        $documentsToDelete = Document::factory()->count(5)->create();
        foreach ($documentsToDelete as $document) {
            $document->delete(); // Soft delete
        }

        $this->command->info('Created sample documents with proper file naming patterns.');
        $this->command->info('Generated documents for suppliers, warehouses, and users.');
        $this->command->info('Created various document types and folders for testing.');
        $this->command->info('Added some soft-deleted documents for restore testing.');
    }

    /**
     * Create fake files in storage for testing downloads.
     */
    private function createFakeFiles(): void
    {
        Storage::fake('public');
        
        $documents = Document::all();
        
        foreach ($documents as $document) {
            $content = "This is a fake file content for {$document->original_name}";
            Storage::disk('public')->put($document->file_path, $content);
        }
        
        $this->command->info('Created fake files in storage for testing.');
    }
}