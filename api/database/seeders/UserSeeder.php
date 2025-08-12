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
        $usersData = [
            $this->createUser('Ana Silva', 'ana.silva@example.com', 'anasilva', 'Biblioteca da Ana', false, true),
            $this->createUser('Carlos Mendes', 'carlos.mendes@example.com', 'carlosmendes', 'Estante do Carlos', false, true),
            $this->createUser('Beatriz Oliveira', 'beatriz.oliveira@example.com', 'biaoliveira', 'Meus Livros Favoritos', true, true),
            $this->createUser('Pedro Santos', 'pedro.santos@example.com', 'pedrosantos', null, false, true),
            $this->createUser('Julia Costa', 'julia.costa@example.com', 'juliacosta', 'Livros da Ju', false, false),
            $this->createUser('Rafael Ferreira', 'rafael.ferreira@example.com', 'rafaelferreira', 'Biblioteca Tech', false, true),
            $this->createUser('Mariana Lima', 'mariana.lima@example.com', 'marianalima', 'Cantinho da Leitura', false, true),
            $this->createUser('Lucas Almeida', 'lucas.almeida@example.com', 'lucasalmeida', null, false, false, null),
            $this->createUser('Fernanda Rodrigues', 'fernanda.rodrigues@example.com', 'fernandarodrigues', 'Leituras da Fê', false, true),
            $this->createUser('Admin User', 'admin@livrolog.com', 'admin', 'Admin Library', false, true, 'Admin', 'admin', 'admin123'),
        ];

        foreach ($usersData as $userData) {
            User::create($userData);
        }
    }

    private function createUser(
        string $displayName,
        string $email,
        string $username,
        ?string $shelfName,
        bool $isPrivate = false,
        bool $emailVerified = true,
        ?string $avatarName = null,
        string $role = 'user',
        string $password = 'password123'
    ): array {
        $nameForAvatar = $avatarName ?? $displayName;

        return [
            'id' => 'U-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
            'display_name' => $displayName,
            'email' => $email,
            'username' => $username,
            'password' => Hash::make($password),
            'avatar' => $nameForAvatar ? 'https://ui-avatars.com/api/?name='.urlencode($nameForAvatar).'&background=random' : null,
            'shelf_name' => $shelfName,
            'locale' => 'pt-BR',
            'role' => $role,
            'is_private' => $isPrivate,
            'email_verified' => $emailVerified,
            'email_verified_at' => $emailVerified ? now() : null,
        ];
    }
}
