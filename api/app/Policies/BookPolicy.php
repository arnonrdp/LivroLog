<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function viewAny(?User $user): bool
    {
        return true; // anyone can list books
    }

    public function view(?User $user, Book $book): bool
    {
        return true; // anyone can view a book
    }

    public function create(?User $user): bool
    {
        return true; // anyone can create a book (add to catalog)
    }

    public function update(User $user, Book $book): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->isAdmin();
    }
}
