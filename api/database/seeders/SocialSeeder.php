<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->count() < 2) {
            $this->command->warn('Need at least 2 users to create social relationships.');

            return;
        }

        // Criar relacionamentos de follows realísticos
        foreach ($users as $user) {
            // Cada usuário segue entre 0-8 pessoas
            $followCount = random_int(0, 8);

            if ($followCount > 0) {
                // Escolher usuários aleatórios para seguir (exceto ele mesmo)
                $potentialFollows = $users->where('id', '!=', $user->id);
                $toFollow = $potentialFollows->random(min($followCount, $potentialFollows->count()));

                foreach ($toFollow as $followedUser) {
                    // Verificar se já não existe o relacionamento
                    $existingFollow = DB::table('follows')
                        ->where('follower_id', $user->id)
                        ->where('followed_id', $followedUser->id)
                        ->exists();

                    if (! $existingFollow) {
                        // Data de follow aleatória nos últimos 6 meses
                        $followDate = now()->subDays(random_int(1, 180));

                        DB::table('follows')->insert([
                            'follower_id' => $user->id,
                            'followed_id' => $followedUser->id,
                            'created_at' => $followDate,
                            'updated_at' => $followDate,
                        ]);
                    }
                }
            }
        }

        // Atualizar contadores de followers/following
        foreach ($users as $user) {
            $followingCount = DB::table('follows')->where('follower_id', $user->id)->count();
            $followersCount = DB::table('follows')->where('followed_id', $user->id)->count();

            $user->update([
                'following_count' => $followingCount,
                'followers_count' => $followersCount,
            ]);
        }
    }
}
