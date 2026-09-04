<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Registration must accept a person's real name.
 *
 * The frontend used to derive a username from the display name and post it, and the server
 * required ^[a-zA-Z0-9_]+$ — so every Brazilian name carrying an accent was rejected with an
 * English validation error about a field the user never saw. The handle is now generated
 * server-side, and these names are the regression guard.
 */
class RegistrationNamesTest extends TestCase
{
    use RefreshDatabase;

    public static function names(): array
    {
        return [
            'acentuado' => ['João Silva', 'joaosilva'],
            'circunflexo e til' => ['José Antônio', 'joseantonio'],
            'agudo duplo' => ['André Luís', 'andreluis'],
            'cedilha' => ['Conceição Araújo', 'conceicaoaraujo'],
            'trema alemão' => ['Jürgen Müller', 'jurgenmuller'],
            'nome longo' => ['Maria Fernanda Oliveira Santos', 'mariafernandaoli'],
            'sem acento' => ['Ana Silva', 'anasilva'],
        ];
    }

    #[DataProvider('names')]
    public function test_a_person_can_register_under_their_own_name(string $displayName, string $expected): void
    {
        $response = $this->postJson('/auth/register', [
            'display_name' => $displayName,
            'email' => 'novo@example.com',
            'password' => 'SenhaForte#2026',
            'password_confirmation' => 'SenhaForte#2026',
            'locale' => 'pt-BR',
        ]);

        $response->assertCreated();
        $this->assertSame($expected, User::where('email', 'novo@example.com')->value('username'));
    }

    public function test_a_name_in_a_non_latin_script_still_registers(): void
    {
        // Nothing transliterates, so the handle falls through to the email prefix rather than
        // failing validation
        $this->postJson('/auth/register', [
            'display_name' => '山田太郎',
            'email' => 'yamada@example.com',
            'password' => 'SenhaForte#2026',
            'password_confirmation' => 'SenhaForte#2026',
        ])->assertCreated();

        $this->assertSame('yamada', User::where('email', 'yamada@example.com')->value('username'));
    }

    public function test_two_people_with_the_same_name_both_register(): void
    {
        foreach (['a@example.com', 'b@example.com'] as $email) {
            $this->postJson('/auth/register', [
                'display_name' => 'João Silva',
                'email' => $email,
                'password' => 'SenhaForte#2026',
                'password_confirmation' => 'SenhaForte#2026',
            ])->assertCreated();
        }

        $this->assertSame('joaosilva', User::where('email', 'a@example.com')->value('username'));
        $this->assertSame('joaosilva1', User::where('email', 'b@example.com')->value('username'));
    }
}
