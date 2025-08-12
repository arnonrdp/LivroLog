<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();
        
        if ($users->isEmpty() || $books->isEmpty()) {
            $this->command->warn('Users or Books not found.');
            return;
        }

        // Criar showcase para alguns usuários (60% de chance)
        foreach ($users as $user) {
            $hasShowcase = rand(1, 100) <= 60;
            
            if ($hasShowcase) {
                // Cada usuário pode ter entre 3-8 livros no showcase
                $showcaseCount = rand(3, 8);
                
                // Escolher livros que o usuário tem na biblioteca
                $userBooks = DB::table('users_books')
                    ->where('user_id', $user->id)
                    ->pluck('book_id')
                    ->toArray();
                
                if (!empty($userBooks)) {
                    // Escolher livros aleatórios da biblioteca do usuário
                    $showcaseBooks = array_slice($userBooks, 0, min($showcaseCount, count($userBooks)));
                    
                    foreach ($showcaseBooks as $index => $bookId) {
                        // Verificar se a tabela showcase existe (se não existir, pular)
                        try {
                            DB::table('showcase')->insert([
                                'id' => 'S-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                                'user_id' => $user->id,
                                'book_id' => $bookId,
                                'position' => $index + 1,
                                'created_at' => now()->subDays(rand(1, 90)),
                                'updated_at' => now()->subDays(rand(1, 30)),
                            ]);
                        } catch (\Exception $e) {
                            // Se a tabela showcase não existir, criar só os dados básicos sem showcase
                            $this->command->info('Showcase table not found, skipping showcase creation.');
                            break;
                        }
                    }
                }
            }
        }
    }
}