<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RealisticReviewSeeder extends Seeder
{
    public function run(): void
    {
        // Pegar apenas usuários que têm livros na biblioteca
        $userBooks = DB::table('users_books')->get();

        if ($userBooks->isEmpty()) {
            $this->command->warn('No user books found. Run UserBookSeeder first.');

            return;
        }

        $reviewTexts = [
            ['title' => 'Excelente leitura!', 'content' => 'Um livro que mudou minha perspectiva sobre muitas coisas. Recomendo fortemente a todos que buscam conhecimento e reflexão.'],
            ['title' => 'Interessante, mas...', 'content' => 'O livro tem pontos interessantes, mas algumas partes são um pouco repetitivas. No geral, vale a pena ler.'],
            ['title' => 'Obra-prima!', 'content' => 'Simplesmente fantástico! Uma narrativa envolvente que prende do início ao fim. Definitivamente está entre os meus favoritos.'],
            ['title' => 'Não atendeu expectativas', 'content' => 'Esperava mais do livro. A história é interessante, mas o desenvolvimento deixa a desejar em alguns aspectos.'],
            ['title' => 'Leitura transformadora', 'content' => 'Este livro realmente me fez pensar sobre a vida de uma forma diferente. Cada página trouxe uma nova reflexão.'],
            ['title' => 'Boa para passar o tempo', 'content' => 'Uma leitura agradável, nada revolucionário, mas entretenimento garantido. Perfeito para relaxar.'],
            ['title' => 'Imperdível!', 'content' => 'Um dos melhores livros que já li! A narrativa é rica e os personagens são muito bem desenvolvidos.'],
            ['title' => 'Decepcionante', 'content' => 'Infelizmente não consegui me conectar com a história. Talvez seja uma questão de gosto pessoal.'],
            ['title' => 'Surpreendente', 'content' => 'Não esperava muito, mas fui agradavelmente surpreendido. Uma leitura que vale cada página.'],
            ['title' => 'Clássico necessário', 'content' => 'Um clássico que todos deveriam ler. Atemporal e sempre relevante.'],
            ['title' => 'Muito técnico', 'content' => 'Livro interessante, mas exige conhecimento prévio do assunto. Não é para iniciantes.'],
            ['title' => 'Envolvente', 'content' => 'Começei a ler e não consegui parar. A história flui de forma natural e prende a atenção.'],
            ['title' => 'Reflexivo', 'content' => 'Uma obra que faz pensar. Cada capítulo traz novas perspectivas sobre temas importantes.'],
            ['title' => 'Divertido', 'content' => 'Uma leitura leve e divertida. Perfeita para quem quer se distrair um pouco.'],
            ['title' => 'Pesado demais', 'content' => 'O tema é interessante, mas o autor torna a leitura muito densa. Precisa de paciência.'],
        ];

        // Para cada usuário-livro, talvez criar uma review (40% de chance)
        foreach ($userBooks as $userBook) {
            $hasReview = random_int(1, 100) <= 40; // 40% de chance de ter review

            if ($hasReview) {
                $reviewData = $reviewTexts[array_rand($reviewTexts)];

                // Algumas reviews têm spoiler
                $isSpoiler = random_int(1, 100) <= 15; // 15% de chance de ter spoiler

                // Diferentes níveis de visibilidade
                $visibilityOptions = ['public', 'public', 'public', 'friends', 'private']; // Maior chance de ser pública
                $visibility = $visibilityOptions[array_rand($visibilityOptions)];

                // Rating entre 1 e 5
                $rating = random_int(1, 5);

                // Algumas reviews recebem votos de helpful
                $helpfulCount = random_int(0, 25);

                // Data da review (após data de leitura, se existir)
                $reviewDate = $userBook->read_at ?
                    \Carbon\Carbon::parse($userBook->read_at)->addDays(random_int(1, 30)) :
                    \Carbon\Carbon::parse($userBook->added_at)->addDays(random_int(1, 60));

                Review::create([
                    'id' => 'R-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
                    'user_id' => $userBook->user_id,
                    'book_id' => $userBook->book_id,
                    'title' => $reviewData['title'],
                    'content' => $reviewData['content'],
                    'rating' => $rating,
                    'visibility_level' => $visibility,
                    'is_spoiler' => $isSpoiler,
                    'helpful_count' => $helpfulCount,
                    'created_at' => $reviewDate,
                    'updated_at' => $reviewDate,
                ]);
            }
        }
    }
}
