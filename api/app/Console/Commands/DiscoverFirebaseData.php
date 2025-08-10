<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DiscoverFirebaseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:discover
                            {--export-path= : Path to Firebase/Firestore export file}
                            {--project-id= : Firebase project ID}
                            {--collection= : Specific collection to analyze}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover and analyze Firebase/Firestore data for migration planning';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Firebase Data Discovery Tool');
        $this->line('');

        // Check for common Firebase export locations
        $this->checkCommonLocations();

        // Check for Firebase config files
        $this->checkConfigFiles();

        // Check for environment variables
        $this->checkEnvironmentVars();

        // Provide next steps
        $this->provideNextSteps();

        return 0;
    }

    /**
     * Check common Firebase export file locations
     */
    private function checkCommonLocations(): void
    {
        $this->info('📁 Checking common export file locations...');

        $commonPaths = [
            'storage/firebase',
            'storage/app/firebase',
            'database/firebase',
            '../firebase-exports',
            '../../firebase-exports',
            storage_path('firebase'),
            storage_path('app/firebase'),
            base_path('firebase-exports'),
        ];

        $found = false;
        foreach ($commonPaths as $path) {
            if (is_dir($path)) {
                $this->line("  ✅ Found directory: {$path}");
                $this->listDirectoryContents($path);
                $found = true;
            } elseif (file_exists($path . '.json')) {
                $this->line("  ✅ Found export file: {$path}.json");
                $this->analyzeJsonFile($path . '.json');
                $found = true;
            }
        }

        if (!$found) {
            $this->warn('  ⚠️  No Firebase export files found in common locations');
        }
        $this->line('');
    }

    /**
     * Check for Firebase configuration files
     */
    private function checkConfigFiles(): void
    {
        $this->info('⚙️  Checking for Firebase configuration files...');

        $configFiles = [
            base_path('firebase.json'),
            base_path('.firebaserc'),
            base_path('firestore.rules'),
            base_path('firestore.indexes.json'),
            resource_path('js/firebase.js'),
            resource_path('js/firebase.ts'),
            base_path('../webapp/src/firebase.js'),
            base_path('../webapp/src/firebase.ts'),
        ];

        $found = false;
        foreach ($configFiles as $file) {
            if (file_exists($file)) {
                $this->line("  ✅ Found config: {$file}");
                $found = true;
            }
        }

        if (!$found) {
            $this->warn('  ⚠️  No Firebase config files found');
        }
        $this->line('');
    }

    /**
     * Check environment variables for Firebase
     */
    private function checkEnvironmentVars(): void
    {
        $this->info('🔐 Checking environment variables...');

        $firebaseVars = [
            'FIREBASE_PROJECT_ID',
            'FIREBASE_CLIENT_EMAIL',
            'FIREBASE_PRIVATE_KEY',
            'FIREBASE_DATABASE_URL',
            'VITE_FIREBASE_API_KEY',
            'VITE_FIREBASE_PROJECT_ID',
            'FIREBASE_API_KEY',
        ];

        $found = false;
        foreach ($firebaseVars as $var) {
            if (env($var)) {
                $this->line("  ✅ Found env var: {$var}");
                $found = true;
            }
        }

        if (!$found) {
            $this->warn('  ⚠️  No Firebase environment variables found');
        }
        $this->line('');
    }

    /**
     * List directory contents
     */
    private function listDirectoryContents(string $path): void
    {
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $fullPath = $path . '/' . $file;
                if (is_file($fullPath)) {
                    $size = filesize($fullPath);
                    $this->line("    📄 {$file} (" . $this->formatBytes($size) . ")");

                    // Analyze JSON files
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                        $this->analyzeJsonFile($fullPath);
                    }
                }
            }
        }
    }

    /**
     * Analyze JSON export file
     */
    private function analyzeJsonFile(string $filePath): void
    {
        try {
            $content = file_get_contents($filePath);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("    ❌ Invalid JSON: " . json_last_error_msg());
                return;
            }

            $this->line("    📊 JSON Analysis:");

            // Check if it's a Firestore export
            if (isset($data['documents'])) {
                $this->line("      🔥 Firestore export detected");
                $this->analyzeFirestoreExport($data);
            } elseif (is_array($data) && isset($data[0])) {
                $this->line("      📚 Array of records detected");
                $this->analyzeArrayData($data);
            } else {
                $this->line("      📝 Custom JSON structure");
                $this->analyzeCustomJson($data);
            }

        } catch (\Exception $e) {
            $this->error("    ❌ Error analyzing file: {$e->getMessage()}");
        }
    }

    /**
     * Analyze Firestore export format
     */
    private function analyzeFirestoreExport(array $data): void
    {
        $collections = [];

        foreach ($data['documents'] as $doc) {
            if (preg_match('/projects\/[^\/]+\/databases\/[^\/]+\/documents\/([^\/]+)/', $doc['name'], $matches)) {
                $collection = $matches[1];
                if (!isset($collections[$collection])) {
                    $collections[$collection] = 0;
                }
                $collections[$collection]++;
            }
        }

        $this->line("      📁 Collections found:");
        foreach ($collections as $collection => $count) {
            $this->line("        - {$collection}: {$count} documents");
        }
    }

    /**
     * Analyze array data
     */
    private function analyzeArrayData(array $data): void
    {
        $totalRecords = count($data);
        $this->line("      📊 Total records: {$totalRecords}");

        if ($totalRecords > 0) {
            $firstItem = $data[0];
            $this->line("      🔑 Fields in first record:");
            foreach (array_keys($firstItem) as $field) {
                $this->line("        - {$field}");
            }
        }
    }

    /**
     * Analyze custom JSON structure
     */
    private function analyzeCustomJson(array $data): void
    {
        $this->line("      🔑 Top-level keys:");
        foreach (array_keys($data) as $key) {
            $value = $data[$key];
            $type = gettype($value);
            if (is_array($value)) {
                $count = count($value);
                $this->line("        - {$key}: array ({$count} items)");
            } else {
                $this->line("        - {$key}: {$type}");
            }
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Provide next steps guidance
     */
    private function provideNextSteps(): void
    {
        $this->info('🎯 Next Steps for Migration:');
        $this->line('');

        $this->line('1. 📥 Export Firebase Data:');
        $this->line('   Use Firebase CLI: firebase firestore:export ./firebase-export');
        $this->line('   Or export from Firebase Console');
        $this->line('');

        $this->line('2. 🔄 Run Migration:');
        $this->line('   php artisan firebase:import --file=/path/to/export.json');
        $this->line('   php artisan firebase:import --url=https://example.com/data.json');
        $this->line('');

        $this->line('3. 📊 Test Migration:');
        $this->line('   php artisan firebase:import --dry-run --file=/path/to/export.json');
        $this->line('');

        $this->line('4. 🗑️  Clear and Import:');
        $this->line('   php artisan firebase:import --clear --file=/path/to/export.json');
        $this->line('');

        $this->info('💡 Need help? Check the documentation or run with --help');
    }
}
