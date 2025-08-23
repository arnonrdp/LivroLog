<?php

namespace App\Events;

use App\Models\Book;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookCreated
{
    use Dispatchable, SerializesModels;

    /**
     * The book that was created.
     */
    public function __construct(
        public Book $book
    ) {
        //
    }
}
