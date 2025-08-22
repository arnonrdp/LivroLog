<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class ListBooksForAsin extends Command
{
    protected $signature = 'books:list-asin';
    protected $description = 'List all books with their IDs, titles, ISBNs and ASINs';

    public function handle()
    {
        $books = Book::select('id', 'title', 'isbn', 'amazon_asin')->get();
        
        $this->info("Total books: " . $books->count());
        $this->info("===========================================");
        
        foreach ($books as $book) {
            $this->line("ID: " . $book->id);
            $this->line("Title: " . $book->title);
            $this->line("ISBN: " . ($book->isbn ?: 'N/A'));
            $this->line("ASIN: " . ($book->amazon_asin ?: 'Not set'));
            $this->info("-------------------------------------------");
        }
        
        return Command::SUCCESS;
    }
}