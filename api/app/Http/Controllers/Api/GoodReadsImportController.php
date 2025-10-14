<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\Providers\AmazonBooksProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GoodReadsImportController extends Controller
{
    /**
     * Upload and process a GoodReads CSV file.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');

        // Parse CSV
        $csvData = $this->parseCsv($file);

        if (! $csvData['valid']) {
            return response()->json([
                'message' => 'Invalid CSV format',
                'error' => $csvData['error'],
            ], 422);
        }

        // Process books
        $stats = [
            'total' => count($csvData['data']),
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        $amazonProvider = app(AmazonBooksProvider::class);

        foreach ($csvData['data'] as $row) {
            $title = trim($row['Title'] ?? '');
            $author = trim($row['Author'] ?? '');
            $isbn = trim($row['ISBN13'] ?? $row['ISBN'] ?? '');

            if (empty($title)) {
                $stats['failed']++;

                continue;
            }

            try {
                // Try to find or create book from Amazon
                $book = $this->findOrCreateBook($title, $author, $isbn, $amazonProvider);

                if (! $book) {
                    $stats['failed']++;
                    $stats['details'][] = [
                        'title' => $title,
                        'status' => 'not_found',
                    ];

                    continue;
                }

                // Check if user already has this book
                if ($request->user()->books()->where('books.id', $book->id)->exists()) {
                    $stats['skipped']++;
                    $stats['details'][] = [
                        'title' => $title,
                        'status' => 'already_exists',
                    ];

                    continue;
                }

                // Add to user's library
                $dateRead = $row['Date Read'] ?? null;
                $request->user()->books()->attach($book->id, [
                    'added_at' => now(),
                    'read_at' => $dateRead ? \Carbon\Carbon::parse($dateRead) : null,
                    'reading_status' => $dateRead ? 'read' : 'want_to_read',
                ]);

                $stats['imported']++;
                $stats['details'][] = [
                    'title' => $title,
                    'status' => 'imported',
                    'book_id' => $book->id,
                ];
            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['details'][] = [
                    'title' => $title,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
                Log::error('Error importing book from GoodReads', [
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Import completed',
            'stats' => $stats,
        ]);
    }

    /**
     * Parse CSV file
     */
    private function parseCsv($file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return ['valid' => false, 'error' => 'Could not open file'];
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return ['valid' => false, 'error' => 'Could not read CSV headers'];
        }

        // Required columns
        $requiredColumns = ['Title', 'Author'];
        $missingColumns = array_diff($requiredColumns, $headers);

        if (! empty($missingColumns)) {
            fclose($handle);

            return [
                'valid' => false,
                'error' => 'Missing required columns: '.implode(', ', $missingColumns),
            ];
        }

        // Read data
        $data = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        return ['valid' => true, 'data' => $data];
    }

    /**
     * Find existing book or create from Amazon
     */
    private function findOrCreateBook(string $title, string $author, string $isbn, AmazonBooksProvider $provider): ?Book
    {
        // Remove GoodReads CSV formatting from ISBN (they use ="..." format)
        $cleanIsbn = preg_replace('/[^0-9X]/i', '', $isbn);

        // Try finding by ISBN
        if (! empty($cleanIsbn)) {
            $book = Book::where('isbn', $cleanIsbn)->first();
            if ($book) {
                return $book;
            }
        }

        // Try finding by title + author
        $book = Book::where('title', 'like', "%{$title}%")
            ->where('authors', 'like', "%{$author}%")
            ->first();

        if ($book) {
            return $book;
        }

        // Search on Amazon PA-API
        $searchQuery = ! empty($cleanIsbn) ? $cleanIsbn : "{$title} {$author}";
        $searchResult = $provider->search($searchQuery, ['region' => 'BR']);

        if (! $searchResult['success'] || empty($searchResult['books'])) {
            Log::warning('Book not found on Amazon', [
                'title' => $title,
                'author' => $author,
                'isbn' => $cleanIsbn,
            ]);

            return null;
        }

        // Get first result
        $amazonBook = $searchResult['books'][0];

        // Create book from Amazon data
        $book = Book::create([
            'id' => 'B-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(5)),
            'title' => $amazonBook['title'],
            'subtitle' => $amazonBook['subtitle'],
            'authors' => $amazonBook['authors'],
            'isbn' => $amazonBook['isbn'] ?? $cleanIsbn,
            'thumbnail' => $amazonBook['thumbnail'],
            'description' => $amazonBook['description'],
            'publisher' => $amazonBook['publisher'],
            'published_date' => $amazonBook['published_date'],
            'page_count' => $amazonBook['page_count'],
            'language' => $amazonBook['language'] ?? 'pt',
            'maturity_rating' => $amazonBook['maturity_rating'],
            'info_link' => $amazonBook['info_link'],
            'preview_link' => $amazonBook['preview_link'],
            'amazon_asin' => $amazonBook['amazon_asin'],
            'asin_status' => 'completed',
            'asin_processed_at' => now(),
            'info_quality' => 'complete',
            'enriched_at' => now(),
        ]);

        Log::info('Created book from Amazon PA-API', [
            'book_id' => $book->id,
            'title' => $book->title,
            'asin' => $book->amazon_asin,
        ]);

        return $book;
    }
}
