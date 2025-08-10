<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\Author;
use App\Models\User;
use App\Exceptions\ImportException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportFirebaseData extends Command
{
    // Constants for display formatting
    private const TITLE_DISPLAY_MAX_LENGTH = 30;
    private const AUTHOR_DISPLAY_MAX_LENGTH = 20;
    private const DEFAULT_BATCH_SIZE = 100;
    private const UNKNOWN_AUTHOR = 'Unknown Author';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:import
                            {--file= : JSON file path containing Firebase export}
                            {--url= : URL to fetch Firebase data from}
                            {--type=all : Data type to import (users,books,all)}
                            {--clear : Clear existing data before import}
                            {--dry-run : Preview the import without making changes}
                            {--batch-size=' . self::DEFAULT_BATCH_SIZE . ' : Number of records to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import all data from Firebase/Firestore to MySQL (users, books)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Firebase Data Migration...');
        $this->line('');

        try {
            // Get input data
            $data = $this->getInputData();

            if (empty($data)) {
                $this->error('❌ No data found to import.');
                return 1;
            }

            // Determine import type
            $type = $this->option('type');

            if ($type === 'all') {
                $this->importAllData($data);
            } else {
                $this->importSpecificData($data, $type);
            }

            $this->info('✅ Migration completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Migration failed: {$e->getMessage()}");
            return 1;
        }
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

        $content = file_get_contents($url);

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
        $this->info('📝 Interactive Firebase Export Guide');
        $this->line('');

        $this->line('To export your Firebase data:');
        $this->line('1. Install Firebase CLI: npm install -g firebase-tools');
        $this->line('2. Login: firebase login');
        $this->line('3. Export Firestore: firebase firestore:export ./export-folder');
        $this->line('4. Or export from Firebase Console > Project Settings > Service Accounts');
        $this->line('');

        if ($this->confirm('Do you have a Firebase export file ready?')) {
            $filePath = $this->ask('Enter the path to your Firebase export JSON file');
            return $this->getDataFromFile($filePath);
        }

        if ($this->confirm('Do you want to use sample data for testing?')) {
            return $this->getSampleData();
        }

        $this->error('No data source provided.');
        return [];
    }

    /**
     * Import all data types
     */
    private function importAllData(array $data): void
    {
        $this->info('📊 Importing all data types...');
        $this->line('');

        // Detect data structure
        if (isset($data['documents'])) {
            // Firestore export format
            $this->importFromFirestoreExport($data);
        } else {
            // Custom format - try to detect collections
            $this->importFromCustomFormat($data);
        }
    }

    /**
     * Import from Firestore export format
     */
    private function importFromFirestoreExport(array $data): void
    {
        $this->info('🔥 Processing Firestore export format...');

        $collections = $this->organizeFirestoreDocuments($data['documents']);

        foreach ($collections as $collectionName => $documents) {
            $this->info("📁 Processing collection: {$collectionName} (" . count($documents) . " documents)");

            switch ($collectionName) {
                case 'users':
                    $this->importUsers($documents);
                    break;
                case 'books':
                    $this->importBooks($documents);
                    break;
                default:
                    $this->warn("⚠️  Unknown collection: {$collectionName} - skipping");
            }
        }
    }

    /**
     * Organize Firestore documents by collection
     */
    private function organizeFirestoreDocuments(array $documents): array
    {
        $collections = [];

        foreach ($documents as $doc) {
            // Extract collection name from document path
            if (preg_match('/projects\/[^\/]+\/databases\/[^\/]+\/documents\/([^\/]+)/', $doc['name'], $matches)) {
                $collection = $matches[1];
                if (!isset($collections[$collection])) {
                    $collections[$collection] = [];
                }

                // Convert Firestore fields to simple array
                $docData = $this->extractFirestoreFields(isset($doc['fields']) ? $doc['fields'] : []);
                $docData['_id'] = basename($doc['name']); // Extract document ID

                $collections[$collection][] = $docData;
            }
        }

        return $collections;
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
            } elseif (isset($value['arrayValue']['values'])) {
                $extracted[$key] = array_map(function($item) {
                    if (isset($item['stringValue'])) {
                        return $item['stringValue'];
                    }
                    if (isset($item['integerValue'])) {
                        return $item['integerValue'];
                    }
                    return $item;
                }, $value['arrayValue']['values']);
            } elseif (isset($value['mapValue']['fields'])) {
                $extracted[$key] = $this->extractFirestoreFields($value['mapValue']['fields']);
            }
        }

        return $extracted;
    }

    /**
     * Import users
     */
    private function importUsers(array $users): void
    {
        if ($this->option('dry-run')) {
            $this->previewUsers($users);
            return;
        }

        if ($this->option('clear')) {
            $this->clearUsers();
        }

        $bar = $this->output->createProgressBar(count($users));
        $bar->start();

        $imported = 0;
        $errors = 0;

        foreach ($users as $userData) {
            try {
                $user = $this->extractUserData($userData);

                User::updateOrCreate(
                    ['email' => $user['email']],
                    $user
                );

                $imported++;
                $bar->advance();
            } catch (\Exception $e) {
                $errors++;
                $this->error("\n❌ Error importing user: {$e->getMessage()}");
                $bar->advance();
            }
        }

        $bar->finish();
        $this->line('');
        $this->info("✅ Users import completed: {$imported} imported, {$errors} errors");
    }

    /**
     * Import books
     */
    private function importBooks(array $books): void
    {
        if ($this->option('dry-run')) {
            $this->previewBooks($books);
            return;
        }

        if ($this->option('clear')) {
            $this->clearBooks();
        }

        $bar = $this->output->createProgressBar(count($books));
        $bar->start();

        $imported = 0;
        $errors = 0;

        foreach ($books as $bookData) {
            try {
                $bookArr = $this->extractBookData($bookData);

                // Check for duplicates
                $existing = Book::where('isbn', $bookArr['isbn'])
                    ->orWhere('title', $bookArr['title'])
                    ->first();

                if (!$existing) {
                    $bookModel = Book::create($bookArr);
                } else {
                    $existing->update($bookArr);
                    $bookModel = $existing;
                }

                // Lidar com autores (array ou string)
                $authors = [];
                if (isset($bookData['authors'])) {
                    if (is_array($bookData['authors'])) {
                        $authors = $bookData['authors'];
                    } elseif (is_string($bookData['authors'])) {
                        // Separar por vírgula se necessário
                        $authors = array_map('trim', explode(',', $bookData['authors']));
                    }
                } else {
                    $authors = [self::UNKNOWN_AUTHOR];
                }

                $authorIds = [];
                foreach ($authors as $authorName) {
                    if (!$authorName) {
                        continue;
                    }
                    $author = Author::firstOrCreate(['name' => $authorName]);
                    $authorIds[] = $author->id;
                }
                $bookModel->authors()->sync($authorIds);

                $imported++;
                $bar->advance();
            } catch (\Exception $e) {
                $errors++;
                $bookTitle = isset($bookData['title']) ? $bookData['title'] : 'Unknown';
                $this->error("\n❌ Error importing book '{$bookTitle}': {$e->getMessage()}");
                $bar->advance();
            }
        }

        $bar->finish();
        $this->line('');
        $this->info("✅ Books import completed: {$imported} imported, {$errors} errors");
    }


    /**
     * Preview methods for dry-run
     */
    private function previewUsers(array $users): void
    {
        $this->info('🔍 Preview Mode - Users (first 5):');
        $this->table(
            ['Display Name', 'Email', 'Username'],
            collect($users)->take(5)->map(fn($user) => [
                $this->getFieldValue($user, ['display_name', 'displayName'], 'Unknown'),
                isset($user['email']) ? $user['email'] : 'No email',
                isset($user['username']) ? $user['username'] : 'No username'
            ])->toArray()
        );
    }

    private function previewBooks(array $books): void
    {
        $this->info('🔍 Preview Mode - Books (first 5):');
        $this->table(
            ['Title', 'Authors', 'ISBN'],
            collect($books)->take(5)->map(fn($book) => [
                substr(isset($book['title']) ? $book['title'] : 'Unknown', 0, self::TITLE_DISPLAY_MAX_LENGTH),
                substr($this->getAuthorsString($book), 0, self::AUTHOR_DISPLAY_MAX_LENGTH),
                $this->getFieldValue($book, ['isbn', 'ISBN'], 'No ISBN')
            ])->toArray()
        );
    }


    /**
     * Clear existing data methods
     */
    private function clearUsers(): void
    {
        if ($this->confirm('⚠️  This will delete all existing users. Continue?', false)) {
            // First clear user-book relationships to avoid FK constraint errors
            DB::table('users_books')->delete();
            DB::table('users')->delete();
            $this->info('🗑️  Users and user-book relationships cleared.');
        }
    }

    private function clearBooks(): void
    {
        if ($this->confirm('⚠️  This will delete all existing books. Continue?', false)) {
            DB::table('users_books')->delete();
            DB::table('books')->delete();
            $this->info('🗑️  Books and user-book relationships cleared.');
        }
    }


    /**
     * Import from custom format
     */
    private function importFromCustomFormat(array $data): void
    {
        $this->info('📝 Processing custom data format...');

        // Try to detect structure
        if (isset($data['users'])) {
            $this->importUsers($data['users']);
        }

        if (isset($data['books'])) {
            $this->importBooks($data['books']);
        }

        if (isset($data['user_books'])) {
            $this->importUserBooks($data['user_books']);
        }

    }

    /**
     * Import specific data type
     */
    private function importSpecificData(array $data, string $type): void
    {
        $this->info("📊 Importing {$type} data...");

        switch ($type) {
            case 'users':
                $users = $data['users'] ?? $data;
                $this->importUsers($users);
                break;
            case 'books':
                $books = $data['books'] ?? $data;
                $this->importBooks($books);
                break;
            default:
                throw ImportException::invalidJson("Unknown data type: {$type}");
        }
    }

    /**
     * Get sample data for testing
     */
    private function getSampleData(): array
    {
        return [
            'users' => [
                [
                    'display_name' => 'João Silva',
                    'email' => 'joao@example.com',
                    'username' => 'joao_silva',
                    'shelf_name' => 'Biblioteca do João',
                    'emailVerified' => true
                ]
            ],
            'books' => [
                [
                    'title' => 'Dom Casmurro',
                    'authors' => ['Machado de Assis'],
                    'isbn' => '9788525406552',
                    'language' => 'pt-BR',
                    'publisher' => 'Globo Livros'
                ]
            ]
        ];
    }

    /**
     * Extract and normalize user data from import
     */
    private function extractUserData(array $userData): array
    {
        return [
            'google_id' => $userData['_id'] ?? null,
            'display_name' => $this->getUserDisplayName($userData),
            'email' => $userData['email'] ?? 'user' . time() . '@example.com',
            'username' => $this->getUserUsername($userData),
            'password' => Hash::make($userData['password'] ?? 'password123'),
            'shelf_name' => $this->getUserShelfName($userData),
            'avatar' => $userData['photoURL'] ?? null,
            'email_verified_at' => (isset($userData['emailVerified']) && $userData['emailVerified']) ? now() : null,
        ];
    }

    /**
     * Get user display name with fallbacks
     */
    private function getUserDisplayName(array $userData): string
    {
        if (isset($userData['display_name'])) {
            return $userData['display_name'];
        }

        if (isset($userData['displayName'])) {
            return $userData['displayName'];
        }

        return 'Unknown User';
    }

    /**
     * Get username with fallbacks
     */
    private function getUserUsername(array $userData): string
    {
        if (isset($userData['username'])) {
            return $userData['username'];
        }

        if (isset($userData['email'])) {
            return $userData['email'];
        }

        return 'user' . time();
    }

    /**
     * Get user shelf name with fallbacks
     */
    private function getUserShelfName(array $userData): ?string
    {
        if (isset($userData['shelf_name'])) {
            return $userData['shelf_name'];
        }

        if (isset($userData['shelfName'])) {
            return $userData['shelfName'];
        }

        return null;
    }

    /**
     * Extract and normalize book data from import
     */
    private function extractBookData(array $bookData): array
    {
        return [
            'title' => $bookData['title'] ?? 'Unknown Title',
            'isbn' => $this->getBookIsbn($bookData),
            'thumbnail' => $this->getBookThumbnail($bookData),
            'language' => $this->getBookLanguage($bookData),
            'publisher' => $bookData['publisher'] ?? null,
            'edition' => $bookData['edition'] ?? null,
        ];
    }

    /**
     * Get book ISBN with fallbacks
     */
    private function getBookIsbn(array $bookData): ?string
    {
        if (isset($bookData['isbn'])) {
            return $bookData['isbn'];
        }

        if (isset($bookData['ISBN'])) {
            return $bookData['ISBN'];
        }

        return null;
    }

    /**
     * Get book thumbnail with fallbacks
     */
    private function getBookThumbnail(array $bookData): ?string
    {
        if (isset($bookData['thumbnail'])) {
            return $bookData['thumbnail'];
        }

        if (isset($bookData['image'])) {
            return $bookData['image'];
        }

        return null;
    }

    /**
     * Get book language with fallbacks
     */
    private function getBookLanguage(array $bookData): string
    {
        if (isset($bookData['language'])) {
            return $bookData['language'];
        }

        if (isset($bookData['lang'])) {
            return $bookData['lang'];
        }

        return 'pt-BR';
    }

    /**
     * Get field value trying multiple possible keys
     */
    private function getFieldValue(array $data, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                return $data[$key];
            }
        }
        return $default;
    }

    /**
     * Get authors string from various possible formats
     */
    private function getAuthorsString(array $book): string
    {
        if (is_array($book['authors'] ?? null)) {
            return implode(', ', $book['authors']);
        }

        if (isset($book['authors'])) {
            return (string) $book['authors'];
        }

        return 'Unknown';
    }

    /**
     * Import user books (books + user_books pivot data)
     */
    private function importUserBooks(array $userBooks): void
    {
        if ($this->option('dry-run')) {
            $this->previewUserBooks($userBooks);
            return;
        }

        if ($this->option('clear')) {
            $this->clearUserBooks();
        }

        $this->info('📚 Importing user books and relationships...');
        $bar = $this->output->createProgressBar(count($userBooks));
        $bar->start();

        $importedBooks = 0;
        $importedRelations = 0;
        $errors = 0;

        foreach ($userBooks as $userBook) {
            try {
                // Extract book data (don't set 'id' - let the model generate it)
                $bookData = [
                    'google_id' => $userBook['id'] ?? $userBook['_id'] ?? null,
                    'title' => $userBook['title'] ?? 'Unknown Title',
                    'authors' => $this->getAuthorsString($userBook),
                    'isbn' => $userBook['ISBN'] ?? $userBook['isbn'] ?? null,
                    'thumbnail' => $this->getBookThumbnail($userBook),
                    'language' => $this->getBookLanguage($userBook),
                ];

                // Create or update book
                $book = Book::updateOrCreate(
                    ['google_id' => $bookData['google_id']],
                    $bookData
                );

                if ($book->wasRecentlyCreated) {
                    $importedBooks++;
                    
                    // Create authors
                    if ($bookData['authors'] !== self::UNKNOWN_AUTHOR) {
                        $this->createAuthorsFromString($bookData['authors']);
                    }
                }

                // Create user-book relationship
                if (isset($userBook['_user_id'])) {
                    // Find user by firebase_id or google_id
                    $user = User::where('google_id', $userBook['_user_id'])->first();
                    
                    if ($user) {
                        // Convert timestamp to date if needed
                        $addedAt = null;
                        $readAt = null;
                        
                        if (isset($userBook['addedIn']) && $userBook['addedIn']) {
                            $addedAt = \Carbon\Carbon::createFromTimestampMs($userBook['addedIn']);
                        }
                        
                        if (isset($userBook['readIn']) && $userBook['readIn'] && $userBook['readIn'] !== '') {
                            if (is_numeric($userBook['readIn'])) {
                                $readAt = \Carbon\Carbon::createFromTimestampMs($userBook['readIn']);
                            } else {
                                $readAt = \Carbon\Carbon::parse($userBook['readIn']);
                            }
                        }

                        // Attach book to user with pivot data
                        $user->books()->syncWithoutDetaching([
                            $book->id => [
                                'added_at' => $addedAt,
                                'read_at' => $readAt,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        ]);
                        
                        $importedRelations++;
                    }
                }

                $bar->advance();
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error importing user book: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->line('');
        $this->info("✅ User books import completed: {$importedBooks} books, {$importedRelations} user-book relations, {$errors} errors");
    }

    /**
     * Preview user books for dry run
     */
    private function previewUserBooks(array $userBooks): void
    {
        $preview = array_slice($userBooks, 0, 5);
        
        $this->info('🔍 Preview Mode - User Books (first 5):');
        $table = [];
        
        foreach ($preview as $userBook) {
            $table[] = [
                $this->truncateDisplay($userBook['title'] ?? 'Unknown', self::TITLE_DISPLAY_MAX_LENGTH),
                $this->getAuthorsString($userBook),
                $userBook['ISBN'] ?? $userBook['isbn'] ?? 'No ISBN',
                $userBook['_user_id'] ?? 'No User ID',
            ];
        }
        
        $this->table(
            ['Title', 'Authors', 'ISBN', 'User ID'],
            $table
        );
    }

    /**
     * Clear user books relationships (not the books themselves)
     */
    private function clearUserBooks(): void
    {
        if ($this->confirm(' ⚠️  This will delete all user-book relationships. Continue?', false)) {
            DB::table('users_books')->delete();
            $this->info('🗑️  User-book relationships cleared');
        }
    }

    /**
     * Create authors from comma-separated string
     */
    private function createAuthorsFromString(string $authorsString): void
    {
        $authors = explode(',', $authorsString);
        
        foreach ($authors as $authorName) {
            $authorName = trim($authorName);
            if ($authorName && $authorName !== self::UNKNOWN_AUTHOR) {
                Author::firstOrCreate(['name' => $authorName]);
            }
        }
    }

    /**
     * Truncate display text for table formatting
     */
    private function truncateDisplay(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        
        return substr($text, 0, $maxLength - 3) . '...';
    }
}
