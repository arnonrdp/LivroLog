<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some users and books to create reviews for
        $users = User::limit(3)->get();
        $books = Book::limit(5)->get();

        if ($users->isEmpty() || $books->isEmpty()) {
            $this->command->info('No users or books found. Please run user and book seeders first.');

            return;
        }

        $reviewsData = [
            [
                'title' => 'Excelente livro!',
                'content' => 'Este livro superou todas as minhas expectativas. A narrativa é envolvente e os personagens são muito bem desenvolvidos. Recomendo para qualquer pessoa que goste de uma boa história.',
                'rating' => 5,
                'visibility_level' => 'public',
                'is_spoiler' => false,
                'helpful_count' => 12,
            ],
            [
                'title' => 'Muito bom, mas poderia ser melhor',
                'content' => 'O livro tem uma premissa interessante e momentos muito bons, mas sinto que alguns capítulos ficaram arrastados. No geral, vale a leitura.',
                'rating' => 4,
                'visibility_level' => 'public',
                'is_spoiler' => false,
                'helpful_count' => 7,
            ],
            [
                'title' => null,
                'content' => 'Não curti muito... a história não me prendeu e achei os personagens meio rasos. Talvez seja questão de gosto mesmo.',
                'rating' => 2,
                'visibility_level' => 'public',
                'is_spoiler' => false,
                'helpful_count' => 3,
            ],
            [
                'title' => 'Review privado',
                'content' => 'Este é um review privado que só eu posso ver. Uso para minhas anotações pessoais sobre o livro.',
                'rating' => 3,
                'visibility_level' => 'private',
                'is_spoiler' => false,
                'helpful_count' => 0,
            ],
            [
                'title' => 'CUIDADO: SPOILERS!',
                'content' => 'Este livro tem uma reviravolta incrível no final quando descobrimos que o protagonista era o vilão o tempo todo! Não esperava por isso.',
                'rating' => 5,
                'visibility_level' => 'public',
                'is_spoiler' => true,
                'helpful_count' => 15,
            ],
        ];

        $createdReviews = 0;

        foreach ($users as $userIndex => $user) {
            foreach ($books as $bookIndex => $book) {
                // Create only some reviews, not all combinations
                if ($createdReviews >= count($reviewsData)) {
                    break 2;
                }

                $reviewData = $reviewsData[$createdReviews];
                $reviewData['user_id'] = $user->id;
                $reviewData['book_id'] = $book->id;

                Review::create($reviewData);
                $createdReviews++;

                $this->command->info("Created review #{$createdReviews} by {$user->display_name} for '{$book->title}'");
            }
        }

        $this->command->info("Created {$createdReviews} sample reviews successfully!");
    }
}
