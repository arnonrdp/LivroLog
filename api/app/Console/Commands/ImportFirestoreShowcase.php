<?php

namespace App\Console\Commands;

use App\Models\Showcase;
use App\Exceptions\ImportException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ImportFirestoreShowcase extends Command
{
    // Constants for display formatting
    private const PREVIEW_TITLE_MAX_LENGTH = 30;
    private const PREVIEW_AUTHORS_MAX_LENGTH = 20;
    private const HTTP_REQUEST_TIMEOUT = 30;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:firestore-showcase
                            {--file= : JSON file path containing Firestore data}
                            {--url= : URL to fetch Firestore data from}
                            {--clear : Clear existing showcase data before import}
                            {--dry-run : Preview the import without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import showcase data from Firestore to MySQL. Accepts JSON file, URL, or manual data entry.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting Firestore Showcase Migration...');

        try {
            $result = $this->executeImport();

            if ($result['success']) {
                $this->info('✅ Migration completed successfully!');
                return 0;
            } else {
                $this->error($result['message']);
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Migration failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Execute the import process
     */
    private function executeImport(): array
    {
        // Get input data
        $data = $this->getInputData();

        if (empty($data)) {
            return [
                'success' => false,
                'message' => '❌ No data found to import.'
            ];
        }

        // Validate data structure
        $validatedData = $this->validateAndTransformData($data);

        if (empty($validatedData)) {
            return [
                'success' => false,
                'message' => '❌ No valid data found after validation.'
            ];
        }

        $this->info("📊 Found {" . count($validatedData) . "} records to import.");

        // Preview mode
        if ($this->option('dry-run')) {
            $this->previewImport($validatedData);
            return ['success' => true, 'message' => 'Preview completed'];
        }

        // Clear existing data if requested
        if ($this->option('clear')) {
            $this->clearExistingData();
        }

        // Import data
        $this->importData($validatedData);

        return ['success' => true, 'message' => 'Migration completed successfully'];
    }

    /**
     * Get input data from various sources
     */
    private function getInputData(): array
    {
        // From file option
        if ($filePath = $this->option('file')) {
            return $this->getDataFromFile($filePath);
        }

        // From URL option
        if ($url = $this->option('url')) {
            return $this->getDataFromUrl($url);
        }

        // Interactive input
        return $this->getDataInteractively();
    }

    /**
     * Get data from JSON file
     */
    private function getDataFromFile(string $filePath): array
    {
        $this->info("📁 Reading data from file: {$filePath}");

        if (!file_exists($filePath)) {
            throw ImportException::fileNotFound($filePath);
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ImportException::invalidJson(json_last_error_msg());
        }

        return $data;
    }

    /**
     * Get data from URL
     */
    private function getDataFromUrl(string $url): array
    {
        $this->info("🌐 Fetching data from URL: {$url}");

        // Validate URL format and scheme
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw ImportException::invalidUrl($url);
        }

        $parsedUrl = parse_url($url);
        if (!in_array($parsedUrl['scheme'] ?? '', ['http', 'https'])) {
            throw ImportException::unsupportedScheme($url);
        }

        // Use stream context for better control
        $context = stream_context_create([
            'http' => [
                'timeout' => self::HTTP_REQUEST_TIMEOUT,
                'user_agent' => 'LivroLog/1.0',
                'follow_location' => false,
            ]
        ]);

        $content = file_get_contents($url, false, $context);

        if ($content === false) {
            throw ImportException::fetchFailed($url);
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ImportException::invalidJson(json_last_error_msg());
        }

        return $data;
    }

    /**
     * Get data interactively
     */
    private function getDataInteractively(): array
    {
        $this->info('📝 Interactive data entry mode');
        $this->line('You can paste JSON data directly or enter "sample" for sample data');

        $input = $this->ask('Enter JSON data or "sample"');

        if (strtolower($input) === 'sample') {
            return $this->getSampleData();
        }

        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ImportException::invalidJson(json_last_error_msg());
        }

        return $data;
    }

    /**
     * Get sample data for testing
     */
    private function getSampleData(): array
    {
        return [
            [
                'title' => 'Dom Casmurro',
                'authors' => 'Machado de Assis',
                'isbn' => '9788525406552',
                'description' => 'Um dos maiores clássicos da literatura brasileira.',
                'thumbnail' => 'https://example.com/dom-casmurro.jpg',
                'publisher' => 'Globo Livros',
                'language' => 'pt-BR',
                'edition' => '1ª edição',
                'notes' => 'Imported from Firestore - Classic Brazilian Literature'
            ],
            [
                'title' => 'O Cortiço',
                'authors' => 'Aluísio Azevedo',
                'isbn' => '9788525406569',
                'description' => 'Romance naturalista brasileiro.',
                'thumbnail' => 'https://example.com/o-cortico.jpg',
                'publisher' => 'Ática',
                'language' => 'pt-BR',
                'edition' => '2ª edição',
                'notes' => 'Imported from Firestore - Naturalist Movement'
            ]
        ];
    }

    /**
     * Validate and transform data
     */
    private function validateAndTransformData(array $data): array
    {
        $validated = [];
        $index = 0;

        foreach ($data as $item) {
            // Handle different Firestore structures
            if (isset($item['fields'])) {
                // Firestore document format
                $item = $this->extractFirestoreFields($item['fields']);
            }

            // Validate required fields
            if (empty($item['title'])) {
                $this->warn("⚠️  Skipping item without title at index {$index}");
                $index++;
                continue;
            }

            // Transform to showcase format
            $showcase = [
                'title' => $item['title'],
                'authors' => $item['authors'] ?? $item['author'] ?? null,
                'isbn' => $item['isbn'] ?? null,
                'description' => $item['description'] ?? $item['desc'] ?? null,
                'thumbnail' => $item['thumbnail'] ?? $item['image'] ?? $item['cover'] ?? null,
                'link' => $item['link'] ?? $item['url'] ?? null,
                'publisher' => $item['publisher'] ?? null,
                'language' => $item['language'] ?? $item['lang'] ?? 'pt-BR',
                'edition' => $item['edition'] ?? null,
                'order_index' => $item['order'] ?? $item['order_index'] ?? $index,
                'is_active' => $item['active'] ?? $item['is_active'] ?? true,
                'notes' => (function () use ($item) {
                    $notes = $item['notes'] ?? '';
                    $importNote = ' - Imported from Firestore';
                    return (strpos($notes, $importNote) === false) ? ($notes . $importNote) : $notes;
                })(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $validated[] = $showcase;
            $index++;
        }

        return $validated;
    }

    /**
     * Extract fields from Firestore document format
     */
    private function extractFirestoreFields(array $fields): array
    {
        $extracted = [];

        foreach ($fields as $key => $value) {
            if (isset($value['stringValue'])) {
                $extracted[$key] = $value['stringValue'];
            } elseif (isset($value['integerValue'])) {
                $extracted[$key] = (int) $value['integerValue'];
            } elseif (isset($value['booleanValue'])) {
                $extracted[$key] = $value['booleanValue'];
            } elseif (isset($value['timestampValue'])) {
                $extracted[$key] = $value['timestampValue'];
            }
        }

        return $extracted;
    }

    /**
     * Preview import
     */
    private function previewImport(array $data): void
    {
        $this->info('🔍 Preview Mode - No changes will be made');
        $this->table(
            ['Title', 'Authors', 'ISBN', 'Language', 'Active'],
            collect($data)->take(10)->map(fn($item) => [
                $this->truncateText($item['title'], self::PREVIEW_TITLE_MAX_LENGTH),
                $this->truncateText($item['authors'] ?? 'N/A', self::PREVIEW_AUTHORS_MAX_LENGTH),
                $item['isbn'] ?? 'N/A',
                $item['language'],
                $item['is_active'] ? 'Yes' : 'No'
            ])->toArray()
        );

        if (count($data) > 10) {
            $this->info("... and " . (count($data) - 10) . " more records");
        }
    }

    /**
     * Clear existing data
     */
    private function clearExistingData(): void
    {
        if ($this->confirm('⚠️  This will delete all existing showcase data. Continue?', false)) {
            DB::table('showcase')->delete();
            $this->info('🗑️  Existing showcase data cleared.');
        } else {
            $this->info('📦 Keeping existing data.');
        }
    }

    /**
     * Import data to database
     */
    private function importData(array $data): void
    {
        $this->info('💾 Importing data...');

        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        $imported = 0;
        $errors = 0;

        DB::transaction(function () use ($data, $bar, &$imported, &$errors) {
            foreach ($data as $item) {
                try {
                    // Check for duplicates by ISBN or by (title and author) combination
                    $existing = Showcase::where('isbn', $item['isbn'])
                        ->orWhere(function ($query) use ($item) {
                            $query->where('title', $item['title'])
                                  ->where('authors', $item['authors']);
                        })
                        ->first();

                    if ($existing) {
                        if ($this->confirm("📚 Book '{$item['title']}' already exists. Update?", true)) {
                            $existing->update($item);
                            $imported++;
                        }
                    } else {
                        Showcase::create($item);
                        $imported++;
                    }

                    $bar->advance();
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("\n❌ Error importing '{$item['title']}': {$e->getMessage()}");
                    $bar->advance();
                }
            }
        });

        $bar->finish();
        $this->line('');
        $this->info("✅ Import completed: {$imported} imported, {$errors} errors");
    }

    /**
     * Truncate text to specified length with ellipsis if needed
     */
    private function truncateText(string $text, int $maxLength): string
    {
        return strlen($text) > $maxLength
            ? substr($text, 0, $maxLength) . '...'
            : $text;
    }
}
