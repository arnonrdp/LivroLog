<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Book;
use App\Models\UserBook;

class FirestoreImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign keys before truncating
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('users_books')->truncate();
        DB::table('books')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Read the JSON file
        $jsonPath = base_path('firestore_data.json');
        if (!file_exists($jsonPath)) {
            $this->command->error('firestore_data.json file does not exist.');
            return;
        }
        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data || !isset($data['users'])) {
            $this->command->error('Invalid firestore_data.json file or missing users field.');
            return;
        }
        $userBooksCount = isset($data['user_books']) && is_array($data['user_books']) ? count($data['user_books']) : 0;
        echo "Total records in user_books: $userBooksCount\n";

        // 1. Map users by Firestore _id
        $firestoreIdToUserId = [];
        $userIdsDebug = [];
        foreach ($data['users'] as $userData) {
            $user = User::create([
                'display_name' => $userData['displayName'] ?? $userData['name'] ?? 'No Name',
                'email' => $userData['email'] ?? null,
                'username' => $userData['username'] ?? null,
                'shelf_name' => $userData['shelfName'] ?? null,
                'password' => bcrypt('senha-temporaria'),
            ]);
            if (!empty($userData['_id'])) {
                $firestoreIdToUserId[$userData['_id']] = $user->id;
                $userIdsDebug[] = $userData['_id'];
            }
        }
        echo "Example of user _id values: ".implode(', ', array_slice($userIdsDebug, 0, 5))."\n";

        // 2. Populate books and users_books from user_books
        $livrosVinculados = 0;
        $userBooksIdsDebug = [];
        $skipped = 0;
        $skippedNoBookData = 0;
        if (!empty($data['user_books'])) {
            foreach ($data['user_books'] as $idx => $userBook) {
                $firestoreUserId = $userBook['_user_id'] ?? null;
                if ($firestoreUserId) {
                    $userBooksIdsDebug[] = $firestoreUserId;
                }
                if (!$firestoreUserId || !isset($firestoreIdToUserId[$firestoreUserId])) {
                    echo "[SKIP] user_books[$idx] without valid _user_id\n";
                    $skipped++;
                    continue;
                }
                // Only process if it has at least a title or ISBN
                $hasBookData = !empty($userBook['title']) || !empty($userBook['ISBN']);
                if (!$hasBookData) {
                    $skippedNoBookData++;
                    // Do not log all to avoid clutter, only the first 10
                    if ($skippedNoBookData <= 10) {
                        echo "[SKIP] user_books[$idx] for user $firestoreUserId missing title and ISBN\n";
                    }
                    $skipped++;
                    continue;
                }
                $userId = $firestoreIdToUserId[$firestoreUserId];

                // Find book by ISBN, otherwise create by title
                $book = null;
                if (!empty($userBook['ISBN'])) {
                    $book = Book::firstOrCreate([
                        'isbn' => $userBook['ISBN']
                    ], [
                        'title' => $userBook['title'] ?? 'No Title',
                        'thumbnail' => $userBook['thumbnail'] ?? null,
                        'language' => $userBook['language'] ?? 'en',
                        'publisher' => $userBook['publisher'] ?? null,
                        'edition' => $userBook['edition'] ?? null,
                    ]);
                } else {
                    $book = Book::firstOrCreate([
                        'title' => $userBook['title'] ?? 'No Title',
                    ], [
                        'thumbnail' => $userBook['thumbnail'] ?? null,
                        'language' => $userBook['language'] ?? 'en',
                        'publisher' => $userBook['publisher'] ?? null,
                        'edition' => $userBook['edition'] ?? null,
                    ]);
                }

                if (!$book || !$book->id) {
                    echo "[ERROR] Book not created correctly for user_id $userId\n";
                    continue;
                }
                echo "Linking book '{$book->title}' (ID: {$book->id}) to user_id $userId\n";

                DB::table('users_books')->insert([
                    'user_id' => $userId,
                    'book_id' => $book->id,
                    'added_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $livrosVinculados++;
            }
        }
        echo "Example of _user_id in user_books: ".implode(', ', array_slice($userBooksIdsDebug, 0, 5))."\n";
        echo "Linked books: $livrosVinculados\n";
        echo "user_books records skipped (missing title/ISBN or invalid user_id): $skipped\n";
        echo "user_books records skipped only for missing title/ISBN: $skippedNoBookData\n";
    }
}
