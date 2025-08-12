<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserBookSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        if ($users->isEmpty() || $books->isEmpty()) {
            $this->command->warn('Users or Books not found. Run UserSeeder and BookSeeder first.');
            return;
        }

        // Para cada usuário, adicionar entre 10-20 livros aleatórios (ou todos se menos de 10)
        foreach ($users as $user) {
            $maxBooks = min(20, $books->count());
            $minBooks = min(10, $books->count());
            $bookCount = random_int($minBooks, $maxBooks);
            $userBooks = $books->random($bookCount);
            
            foreach ($userBooks as $book) {
                // Algumas pessoas têm data de leitura, outras não
                $hasReadDate = random_int(1, 100) <= 60; // 60% de chance de ter data de leitura
                $readAt = null;
                
                if ($hasReadDate) {
                    // Data de leitura aleatória nos últimos 2 anos
                    $readAt = now()->subDays(random_int(1, 730));
                }
                
                // Data de adição à biblioteca (sempre anterior à data de leitura)
                $addedAt = $readAt ? $readAt->copy()->subDays(random_int(1, 30)) : now()->subDays(random_int(1, 365));

                DB::table('users_books')->insert([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'added_at' => $addedAt,
                    'read_at' => $readAt,
                    'created_at' => $addedAt,
                    'updated_at' => $readAt ?? $addedAt,
                ]);
            }
        }
    }
}