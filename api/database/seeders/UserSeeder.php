<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'id' => 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'display_name' => 'Ana Silva',
                'email' => 'ana.silva@example.com',
                'username' => 'anasilva',
                'password' => Hash::make('password123'),
                'avatar' => 'https://ui-avatars.com/api/?name=Ana+Silva&background=random',
                'shelf_name' => 'Biblioteca da Ana',
                'locale' => 'pt-BR',
                'role' => 'user',
                'is_private' => false,
                'email_verified' => true,
                'email_verified_at' => now(),
            ],
            [
                'id' => 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'display_name' => 'Carlos Mendes',
                'email' => 'carlos.mendes@example.com',
                'username' => 'carlosmendes',
                'password' => Hash::make('password123'),
                'avatar' => 'https://ui-avatars.com/api/?name=Carlos+Mendes&background=random',
                'shelf_name' => 'Estante do Carlos',
                'locale' => 'pt-BR',
                'role' => 'user',
                'is_private' => false,
                'email_verified' => true,
                'email_verified_at' => now(),
            ],
            [
                'id' => 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'display_name' => 'Beatriz Oliveira',
                'email' => 'beatriz.oliveira@example.com',
                'username' => 'biaoliveira',
                'password' => Hash::make('password123'),
                'avatar' => 'https://ui-avatars.com/api/?name=Beatriz+Oliveira&background=random',
                'shelf_name' => 'Meus Livros Favoritos',
                'locale' => 'pt-BR',
                'role' => 'user',
                'is_private' => true,
                'email_verified' => true,
                'email_verified_at' => now(),
            ],
            [
                'id' => 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'display_name' => 'Pedro Santos',
                'email' => 'pedro.santos@example.com',
                'username' => 'pedrosantos',
                'password' => Hash::make('password123'),
                'avatar' => 'https://ui-avatars.com/api/?name=Pedro+Santos&background=random',
                'shelf_name' => null,
                'locale' => 'pt-BR',
                'role' => 'user',
                'is_private' => false,
                'email_verified' => true,
                'email_verified_at' => now(),
            ],
            [
                'id' => 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'display_name' => 'Julia Costa',
                'email' => 'julia.costa@example.com',
                'username' => 'juliacosta',
                'password' => Hash::make('password123'),
                'avatar' => 'https://ui-avatars.com/api/?name=Julia+Costa&background=random',
                'shelf_name' => 'Livros da Ju',
                'locale' => 'pt-BR',
                'role' => 'user',
                'is_private' => false,
                'email_verified' => false,
            ],
            [
                'id' => 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'display_name' => 'Rafael Ferreira',
                'email' => 'rafael.ferreira@example.com',
                'username' => 'rafaelferreira',
                'password' => Hash::make('password123'),
                'avatar' => 'https://ui-avatars.com/api/?name=Rafael+Ferreira&background=random',
                'shelf_name' => 'Biblioteca Tech',
                'locale' => 'pt-BR',
                'role' => 'user',
                'is_private' => false,
                'email_verified' => true,
                'email_verified_at' => now(),
            ],
            [
                'id' => 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'display_name' => 'Mariana Lima',
                'email' => 'mariana.lima@example.com',
                'username' => 'marianalima',
                'password' => Hash::make('password123'),
                'avatar' => 'https://ui-avatars.com/api/?name=Mariana+Lima&background=random',
                'shelf_name' => 'Cantinho da Leitura',
                'locale' => 'pt-BR',
                'role' => 'user',
                'is_private' => false,
                'email_verified' => true,
                'email_verified_at' => now(),
            ],
            [
                'id' => 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'display_name' => 'Lucas Almeida',
                'email' => 'lucas.almeida@example.com',
                'username' => 'lucasalmeida',
                'password' => Hash::make('password123'),
                'avatar' => null,
                'shelf_name' => null,
                'locale' => 'pt-BR',
                'role' => 'user',
                'is_private' => false,
                'email_verified' => false,
            ],
            [
                'id' => 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'display_name' => 'Fernanda Rodrigues',
                'email' => 'fernanda.rodrigues@example.com',
                'username' => 'fernandarodrigues',
                'password' => Hash::make('password123'),
                'avatar' => 'https://ui-avatars.com/api/?name=Fernanda+Rodrigues&background=random',
                'shelf_name' => 'Leituras da Fê',
                'locale' => 'pt-BR',
                'role' => 'user',
                'is_private' => false,
                'email_verified' => true,
                'email_verified_at' => now(),
            ],
            [
                'id' => 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'display_name' => 'Admin User',
                'email' => 'admin@livrolog.com',
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'avatar' => 'https://ui-avatars.com/api/?name=Admin&background=random',
                'shelf_name' => 'Admin Library',
                'locale' => 'pt-BR',
                'role' => 'admin',
                'is_private' => false,
                'email_verified' => true,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }
    }
}