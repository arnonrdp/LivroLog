<?php

namespace Database\Seeders;

use App\Models\Showcase;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShowcaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $showcaseBooks = [
            [
                'title' => 'The Lord of the Rings',
                'authors' => 'J.R.R. Tolkien',
                'isbn' => '9780544003415',
                'description' => 'One ring to rule them all. The greatest fantasy epic of our time.',
                'thumbnail' => 'https://books.google.com/books/content?id=aWZzLPhY4o0C&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'link' => 'https://books.google.com/books?id=aWZzLPhY4o0C',
                'publisher' => 'Houghton Mifflin Harcourt',
                'language' => 'en',
                'order_index' => 1,
                'is_active' => true,
                'notes' => 'Classic fantasy masterpiece'
            ],
            [
                'title' => 'The Hobbit',
                'authors' => 'J.R.R. Tolkien',
                'isbn' => '9780547928227',
                'description' => 'A reluctant hobbit, Bilbo Baggins, sets out to the Lonely Mountain with a spirited group of dwarves to reclaim their mountain home—and the gold within it—from the dragon Smaug.',
                'thumbnail' => 'https://books.google.com/books/content?id=pD6arNyKyi8C&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'link' => 'https://books.google.com/books?id=pD6arNyKyi8C',
                'publisher' => 'Houghton Mifflin Harcourt',
                'language' => 'en',
                'order_index' => 2,
                'is_active' => true,
                'notes' => 'Perfect introduction to Middle-earth'
            ],
            [
                'title' => '1984',
                'authors' => 'George Orwell',
                'isbn' => '9780452284234',
                'description' => 'A dystopian novel that explores themes of totalitarianism, surveillance, and individual freedom.',
                'thumbnail' => 'https://books.google.com/books/content?id=kotPYEqx7kMC&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'link' => 'https://books.google.com/books?id=kotPYEqx7kMC',
                'publisher' => 'Plume Books',
                'language' => 'en',
                'order_index' => 3,
                'is_active' => true,
                'notes' => 'Timeless dystopian classic'
            ],
            [
                'title' => 'To Kill a Mockingbird',
                'authors' => 'Harper Lee',
                'isbn' => '9780061120084',
                'description' => 'A gripping, heart-wrenching, and wholly remarkable tale of coming-of-age in a South poisoned by virulent prejudice.',
                'thumbnail' => 'https://books.google.com/books/content?id=PGR2AwAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'link' => 'https://books.google.com/books?id=PGR2AwAAQBAJ',
                'publisher' => 'Harper Perennial Modern Classics',
                'language' => 'en',
                'order_index' => 4,
                'is_active' => true,
                'notes' => 'American literature classic'
            ],
            [
                'title' => 'Pride and Prejudice',
                'authors' => 'Jane Austen',
                'isbn' => '9780141439518',
                'description' => 'A romantic novel that critiques the British landed gentry at the end of the 18th century.',
                'thumbnail' => 'https://books.google.com/books/content?id=s1gVAAAAYAAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'link' => 'https://books.google.com/books?id=s1gVAAAAYAAJ',
                'publisher' => 'Penguin Classics',
                'language' => 'en',
                'order_index' => 5,
                'is_active' => true,
                'notes' => 'Romantic literature masterpiece'
            ]
        ];

        foreach ($showcaseBooks as $book) {
            Showcase::create($book);
        }
    }
}
