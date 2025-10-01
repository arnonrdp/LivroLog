<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class UpdateBookAsin extends Command
{
    protected $signature = 'books:set-asin {book_id} {asin}';

    protected $description = 'Update the Amazon ASIN for a specific book';

    public function handle()
    {
        $bookId = $this->argument('book_id');
        $asin = $this->argument('asin');

        $book = Book::find($bookId);

        if (! $book) {
            $this->error("Book with ID {$bookId} not found!");

            return Command::FAILURE;
        }

        $book->amazon_asin = $asin;
        $book->save();

        $this->info("✓ Updated ASIN for book: {$book->title}");
        $this->info("  New ASIN: {$asin}");
        $this->info("  Amazon link: https://www.amazon.com/dp/{$asin}?tag=livrolog-20");

        return Command::SUCCESS;
    }
}
