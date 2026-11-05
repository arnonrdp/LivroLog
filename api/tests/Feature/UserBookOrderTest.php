<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserBookOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_order_is_saved_and_returned_without_touching_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        [$a, $b, $c] = Book::factory()->count(3)->create();

        foreach ([$a, $b, $c] as $book) {
            $user->books()->attach($book->id, ['added_at' => now()]);
        }
        $other->books()->attach($a->id, ['added_at' => now(), 'position' => 7]);

        Sanctum::actingAs($user);

        $this->putJson('/user/books/order', ['book_ids' => [$c->id, $a->id]])->assertOk();

        $positions = collect($this->getJson('/user/books')->assertOk()->json())
            ->mapWithKeys(fn ($book) => [$book['id'] => $book['pivot']['position']]);

        $this->assertSame([$c->id => 0, $a->id => 1, $b->id => null], [
            $c->id => $positions[$c->id],
            $a->id => $positions[$a->id],
            $b->id => $positions[$b->id],
        ]);
        $this->assertDatabaseHas('users_books', ['user_id' => $other->id, 'book_id' => $a->id, 'position' => 7]);
    }

    public function test_order_requires_a_list(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/user/books/order', [])->assertUnprocessable();
    }
}
