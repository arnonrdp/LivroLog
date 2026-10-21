<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShelfTextureTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_is_saved_on_the_owner_and_visible_to_visitors(): void
    {
        $owner = User::factory()->create(['is_private' => false]);
        $visitor = User::factory()->create(['shelf_texture' => 'slate']);
        $this->actingAs($owner)->putJson('/auth/me', ['shelf_texture' => 'glass'])
            ->assertOk()->assertJsonPath('user.shelf_texture', 'glass');
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'shelf_texture' => 'glass']);
        $this->getJson('/auth/me')->assertOk()->assertJsonPath('shelf_texture', 'glass');
        $this->actingAs($visitor)->getJson('/users/'.$owner->username)
            ->assertOk()->assertJsonPath('shelf_texture', 'glass');
        $this->assertSame('slate', $visitor->fresh()->shelf_texture);
    }

    public function test_public_profile_exposes_material_without_login(): void
    {
        $owner = User::factory()->create(['is_private' => false, 'shelf_texture' => 'marble']);
        $this->getJson('/users/'.$owner->username)->assertOk()->assertJsonPath('shelf_texture', 'marble');
    }

    public function test_invalid_material_cannot_replace_saved_selection(): void
    {
        $owner = User::factory()->create(['shelf_texture' => 'glass']);
        foreach (['invalid', '../glass', '', null] as $value) {
            $this->actingAs($owner)->putJson('/auth/me', ['shelf_texture' => $value])
                ->assertUnprocessable()->assertJsonValidationErrors('shelf_texture');
        }
        $this->assertSame('glass', $owner->fresh()->shelf_texture);
    }

    public function test_default_and_unrelated_profile_updates_preserve_material(): void
    {
        $owner = User::factory()->create();
        $this->assertSame('wood', $owner->fresh()->shelf_texture);
        $this->actingAs($owner)->putJson('/auth/me', ['shelf_texture' => 'steel'])->assertOk();
        $this->putJson('/auth/me', ['shelf_name' => 'My shelf'])->assertOk();
        $this->assertSame('steel', $owner->fresh()->shelf_texture);
    }

    public function test_guests_cannot_change_a_material(): void
    {
        $this->putJson('/auth/me', ['shelf_texture' => 'glass'])->assertUnauthorized();
    }
}
