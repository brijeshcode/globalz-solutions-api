<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extensions = ['pdf', 'doc', 'docx', 'jpg', 'png', 'xlsx', 'xls'];
        $extension = $this->faker->randomElement($extensions);
        $originalNameWithoutExt = str_replace(' ', '-', $this->faker->words(3, true));
        $originalName = $originalNameWithoutExt . '.' . $extension;
        
        // Generate filename with new pattern: modeltype-id-date-time-originalname.extension
        $modelTypes = ['supplier', 'warehouse', 'user', 'general'];
        $modelType = $this->faker->randomElement($modelTypes);
        $modelId = $this->faker->numberBetween(1, 9999);
        $currentDate = date('d-m-Y');
        $currentTime = date('H-i-s');
        $fileName = "{$modelType}-{$modelId}-{$currentDate}-{$currentTime}-{$originalNameWithoutExt}.{$extension}";
        
        // Generate MIME type based on extension
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel'
        ];

        $documentTypes = [
            'contract', 'invoice', 'certificate', 'photo', 'manual', 
            'specification', 'report', 'presentation', 'legal', 'warranty'
        ];

        $folders = [
            'contracts', 'invoices', 'certificates', 'photos', 'manuals',
            'specifications', 'reports', 'legal', 'admin', 'archive'
        ];

        return [
            'original_name' => $originalName,
            'file_name' => $fileName,
            'file_path' => $modelType . '/documents/' . date('Y/m') . '/' . $fileName,
            'file_size' => $this->faker->numberBetween(1024, 10485760), // 1KB to 10MB
            'mime_type' => $mimeTypes[$extension],
            'file_extension' => $extension,
            'title' => $this->faker->optional(0.7)->sentence(4),
            'description' => $this->faker->optional(0.5)->paragraph(),
            'document_type' => $this->faker->optional(0.8)->randomElement($documentTypes),
            'folder' => $this->faker->optional(0.6)->randomElement($folders),
            'tags' => $this->faker->optional(0.4)->randomElements(
                ['important', 'confidential', 'public', 'internal', 'external', 'draft', 'final'],
                $this->faker->numberBetween(1, 3)
            ),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_public' => $this->faker->boolean(30), // 30% chance of being public
            'is_featured' => $this->faker->boolean(10), // 10% chance of being featured
            'metadata' => $this->faker->optional(0.3)->randomElements([
                'width' => $this->faker->numberBetween(100, 2000),
                'height' => $this->faker->numberBetween(100, 2000),
                'pages' => $this->faker->numberBetween(1, 50),
                'author' => $this->faker->name(),
                'created_date' => $this->faker->date(),
            ], $this->faker->numberBetween(1, 3), true),
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * Create a PDF document.
     */
    public function pdf(): static
    {
        return $this->state(function (array $attributes) {
            $originalNameWithoutExt = str_replace(' ', '-', $this->faker->words(3, true));
            $modelType = $this->faker->randomElement(['supplier', 'warehouse', 'user', 'general']);
            $modelId = $this->faker->numberBetween(1, 9999);
            $currentDate = date('d-m-Y');
            $currentTime = date('H-i-s');
            $fileName = "{$modelType}-{$modelId}-{$currentDate}-{$currentTime}-{$originalNameWithoutExt}.pdf";
            
            return [
                'original_name' => $originalNameWithoutExt . '.pdf',
                'file_name' => $fileName,
                'file_path' => $modelType . '/documents/' . date('Y/m') . '/' . $fileName,
                'mime_type' => 'application/pdf',
                'file_extension' => 'pdf',
                'document_type' => $this->faker->randomElement(['contract', 'invoice', 'report', 'manual']),
            ];
        });
    }

    /**
     * Create an image document.
     */
    public function image(): static
    {
        return $this->state(function (array $attributes) {
            $extension = $this->faker->randomElement(['jpg', 'png']);
            $mimeType = $extension === 'jpg' ? 'image/jpeg' : 'image/png';
            $originalNameWithoutExt = str_replace(' ', '-', $this->faker->words(2, true));
            $modelType = $this->faker->randomElement(['supplier', 'warehouse', 'user', 'general']);
            $modelId = $this->faker->numberBetween(1, 9999);
            $currentDate = date('d-m-Y');
            $currentTime = date('H-i-s');
            $fileName = "{$modelType}-{$modelId}-{$currentDate}-{$currentTime}-{$originalNameWithoutExt}.{$extension}";
            
            return [
                'original_name' => $originalNameWithoutExt . '.' . $extension,
                'file_name' => $fileName,
                'file_path' => $modelType . '/documents/' . date('Y/m') . '/' . $fileName,
                'mime_type' => $mimeType,
                'file_extension' => $extension,
                'document_type' => 'photo',
                'metadata' => [
                    'width' => $this->faker->numberBetween(500, 2000),
                    'height' => $this->faker->numberBetween(500, 2000),
                    'resolution' => $this->faker->randomElement(['72dpi', '150dpi', '300dpi']),
                ],
            ];
        });
    }

    /**
     * Create a Word document.
     */
    public function wordDocument(): static
    {
        return $this->state(function (array $attributes) {
            $extension = $this->faker->randomElement(['doc', 'docx']);
            $mimeType = $extension === 'doc' 
                ? 'application/msword' 
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            $originalNameWithoutExt = str_replace(' ', '-', $this->faker->words(3, true));
            $modelType = $this->faker->randomElement(['supplier', 'warehouse', 'user', 'general']);
            $modelId = $this->faker->numberBetween(1, 9999);
            $currentDate = date('d-m-Y');
            $currentTime = date('H-i-s');
            $fileName = "{$modelType}-{$modelId}-{$currentDate}-{$currentTime}-{$originalNameWithoutExt}.{$extension}";
            
            return [
                'original_name' => $originalNameWithoutExt . '.' . $extension,
                'file_name' => $fileName,
                'file_path' => $modelType . '/documents/' . date('Y/m') . '/' . $fileName,
                'mime_type' => $mimeType,
                'file_extension' => $extension,
                'document_type' => $this->faker->randomElement(['report', 'manual', 'specification']),
                'metadata' => [
                    'pages' => $this->faker->numberBetween(1, 50),
                    'words' => $this->faker->numberBetween(100, 10000),
                    'author' => $this->faker->name(),
                ],
            ];
        });
    }

    /**
     * Create a spreadsheet document.
     */
    public function spreadsheet(): static
    {
        return $this->state(function (array $attributes) {
            $extension = $this->faker->randomElement(['xls', 'xlsx']);
            $mimeType = $extension === 'xls' 
                ? 'application/vnd.ms-excel' 
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $originalNameWithoutExt = str_replace(' ', '-', $this->faker->words(2, true));
            $modelType = $this->faker->randomElement(['supplier', 'warehouse', 'user', 'general']);
            $modelId = $this->faker->numberBetween(1, 9999);
            $currentDate = date('d-m-Y');
            $currentTime = date('H-i-s');
            $fileName = "{$modelType}-{$modelId}-{$currentDate}-{$currentTime}-{$originalNameWithoutExt}.{$extension}";
            
            return [
                'original_name' => $originalNameWithoutExt . '.' . $extension,
                'file_name' => $fileName,
                'file_path' => $modelType . '/documents/' . date('Y/m') . '/' . $fileName,
                'mime_type' => $mimeType,
                'file_extension' => $extension,
                'document_type' => $this->faker->randomElement(['report', 'invoice', 'specification']),
                'metadata' => [
                    'sheets' => $this->faker->numberBetween(1, 10),
                    'rows' => $this->faker->numberBetween(50, 1000),
                    'columns' => $this->faker->numberBetween(5, 50),
                ],
            ];
        });
    }

    /**
     * Create a featured document.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ]);
    }

    /**
     * Create a public document.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Create a document with specific document type.
     */
    public function type(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => $type,
        ]);
    }

    /**
     * Create a document in specific folder.
     */
    public function folder(string $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'folder' => $folder,
        ]);
    }
}