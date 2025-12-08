<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can register with valid data.
     */
    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/auth/register', [
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'password' => 'SecurePass@123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'display_name',
                    'username',
                ],
                'access_token',
                'token_type',
            ])
            ->assertJson([
                'token_type' => 'Bearer',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'username' => 'johndoe',
        ]);
    }

    /**
     * Test registration fails with invalid email.
     */
    public function test_registration_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/auth/register', [
            'display_name' => 'John Doe',
            'email' => 'invalid-email',
            'username' => 'johndoe',
            'password' => 'SecurePass@123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration fails with weak password.
     */
    public function test_registration_fails_with_weak_password(): void
    {
        $response = $this->postJson('/auth/register', [
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'password' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test registration fails with password missing uppercase.
     */
    public function test_registration_fails_with_password_missing_uppercase(): void
    {
        $response = $this->postJson('/auth/register', [
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'password' => 'securepass@123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test registration fails with password missing special character.
     */
    public function test_registration_fails_with_password_missing_special_char(): void
    {
        $response = $this->postJson('/auth/register', [
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'johndoe',
            'password' => 'SecurePass123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test registration fails with duplicate email.
     */
    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/auth/register', [
            'display_name' => 'John Doe',
            'email' => 'existing@example.com',
            'username' => 'johndoe',
            'password' => 'SecurePass@123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration fails with duplicate username.
     */
    public function test_registration_fails_with_duplicate_username(): void
    {
        User::factory()->create(['username' => 'existinguser']);

        $response = $this->postJson('/auth/register', [
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'existinguser',
            'password' => 'SecurePass@123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    /**
     * Test registration fails with reserved username.
     */
    public function test_registration_fails_with_reserved_username(): void
    {
        // Test only one reserved username to avoid rate limiting
        $response = $this->postJson('/auth/register', [
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'admin',
            'password' => 'SecurePass@123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    /**
     * Test registration fails with username containing invalid characters.
     */
    public function test_registration_fails_with_invalid_username_characters(): void
    {
        $response = $this->postJson('/auth/register', [
            'display_name' => 'John Doe',
            'email' => 'john@example.com',
            'username' => 'john-doe!',
            'password' => 'SecurePass@123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    /**
     * Test user can login with valid credentials.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('SecurePass@123'),
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePass@123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'display_name',
                    'username',
                ],
                'access_token',
                'token_type',
            ])
            ->assertJson([
                'token_type' => 'Bearer',
            ]);
    }

    /**
     * Test login fails with invalid credentials.
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('SecurePass@123'),
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test login fails with non-existent email.
     */
    public function test_login_fails_with_non_existent_email(): void
    {
        $response = $this->postJson('/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'SecurePass@123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test login returns bearer token format.
     */
    public function test_login_returns_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('SecurePass@123'),
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePass@123',
        ]);

        $response->assertStatus(200);

        $token = $response->json('access_token');
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        // Sanctum tokens contain a pipe character separating the ID from the token
        $this->assertStringContainsString('|', $token);
    }

    /**
     * Test authenticated user can logout.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        // Create a real token in the database
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);
    }

    /**
     * Test logout invalidates token.
     */
    public function test_logout_invalidates_token(): void
    {
        $user = User::factory()->create();

        // Create a real token for the user
        $token = $user->createToken('test_token')->plainTextToken;

        // Verify token works before logout
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/auth/me')
            ->assertStatus(200);

        // Logout
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/auth/logout')
            ->assertStatus(200);

        // Verify token was deleted from database
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Test authenticated user can get profile.
     */
    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create([
            'display_name' => 'John Doe',
            'username' => 'johndoe',
        ]);

        $response = $this->actingAs($user)->getJson('/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'display_name',
                'username',
                'email',
                'email_verified',
                'has_password_set',
                'has_google_connected',
            ])
            ->assertJson([
                'display_name' => 'John Doe',
                'username' => 'johndoe',
            ]);
    }

    /**
     * Test unauthenticated request returns 401.
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/auth/me');

        $response->assertStatus(401);
    }

    /**
     * Test user can update profile.
     */
    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'display_name' => 'John Doe',
            'username' => 'johndoe',
        ]);

        $response = $this->actingAs($user)->putJson('/auth/me', [
            'display_name' => 'Jane Doe',
            'shelf_name' => "Jane's Library",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Profile updated successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'display_name' => 'Jane Doe',
            'shelf_name' => "Jane's Library",
        ]);
    }

    /**
     * Test user can toggle privacy setting.
     */
    public function test_user_can_toggle_privacy_setting(): void
    {
        $user = User::factory()->create(['is_private' => false]);

        $response = $this->actingAs($user)->putJson('/auth/me', [
            'is_private' => true,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_private' => true,
        ]);
    }

    /**
     * Test user can delete account.
     */
    public function test_user_can_delete_account(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $response = $this->actingAs($user)->deleteJson('/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account deleted successfully',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $userId,
        ]);
    }

    /**
     * Test registration with locale sets user locale.
     */
    public function test_registration_with_locale_sets_user_locale(): void
    {
        $response = $this->postJson('/auth/register', [
            'display_name' => 'João Silva',
            'email' => 'joao@example.com',
            'username' => 'joaosilva',
            'password' => 'SecurePass@123',
            'locale' => 'pt-BR',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'joao@example.com',
            'locale' => 'pt',
        ]);
    }

    /**
     * Test user can change password.
     */
    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'email' => 'changepass@example.com',
            'password' => bcrypt('OldPass@123'),
        ]);

        // Login to get a real token
        $loginResponse = $this->postJson('/auth/login', [
            'email' => 'changepass@example.com',
            'password' => 'OldPass@123',
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/auth/password', [
                'current_password' => 'OldPass@123',
                'password' => 'NewPass@456',
                'password_confirmation' => 'NewPass@456',
            ]);

        $response->assertStatus(200);

        // Verify password was changed by checking database hash
        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPass@456', $user->password));
    }

    /**
     * Test changing password with wrong current password fails.
     */
    public function test_changing_password_with_wrong_current_password_fails(): void
    {
        $user = User::factory()->create([
            'email' => 'wrongpass@example.com',
            'password' => bcrypt('OldPass@123'),
        ]);

        // Login to get a real token
        $loginResponse = $this->postJson('/auth/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'OldPass@123',
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/auth/password', [
                'current_password' => 'WrongPassword!1',
                'password' => 'NewPass@456',
                'password_confirmation' => 'NewPass@456',
            ]);

        $response->assertStatus(422);
    }
}
