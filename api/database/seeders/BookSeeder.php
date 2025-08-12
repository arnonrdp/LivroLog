<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $books = $this->getBooksData();

        foreach ($books as $book) {
            Book::create($this->createBook(...$book));
        }
    }

    private function getBooksData(): array
    {
        return [
            [
                '9780771038525', 'Y41zAwAAQBAJ',
                [['type' => 'ISBN_10', 'identifier' => '0771038526'], ['type' => 'ISBN_13', 'identifier' => '9780771038525']],
                'Sapiens', 'A Brief History of Humankind', 'Yuval Noah Harari',
                'NATIONAL BESTSELLER - Destined to become a modern classic in the vein of Guns, Germs, and Steel, Sapiens is a lively, groundbreaking history of humankind told from a unique perspective.',
                'https://books.google.com/books/content?id=Y41zAwAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'McClelland & Stewart', '2014-10-28', 512, 'ebook',
                ['History / Civilization', 'Social Science / Anthropology / Cultural & Social', 'Science / Life Sciences / Evolution'],
            ],
            [
                '9780804139304', 'ZH4oAwAAQBAJ',
                [['type' => 'ISBN_10', 'identifier' => '080413930X'], ['type' => 'ISBN_13', 'identifier' => '9780804139304']],
                'Zero to One', 'Notes on Startups, or How to Build the Future', 'Peter Thiel, Blake Masters',
                '#1 NEW YORK TIMES BESTSELLER • "This book delivers completely new and refreshing ideas on how to create value in the world."—Mark Zuckerberg, CEO of Meta',
                'https://books.google.com/books/content?id=ZH4oAwAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'Crown', '2014-09-16', 224, 'ebook',
                ['Business & Economics / Entrepreneurship', 'Business & Economics / Small Business'],
            ],
            [
                '9896530076', 'Fy_wwAEACAAJ',
                [['type' => 'ISBN_10', 'identifier' => '9896530076'], ['type' => 'ISBN_13', 'identifier' => '9789896530075']],
                'Capitães da areia', 'romance', 'Jorge Amado, Pedro Costa',
                'Romance clássico da literatura brasileira que retrata a vida de meninos de rua em Salvador.',
                'https://books.google.com/books/content?id=Fy_wwAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api',
                'Leya', '2009-01-01', 286, 'paperback',
                ['Literatura Brasileira', 'Clássicos'],
            ],
            [
                '9781781101063', 'ox9BiuVKM1cC',
                [['type' => 'ISBN_10', 'identifier' => '178110106X'], ['type' => 'ISBN_13', 'identifier' => '9781781101063']],
                'Harry Potter et la Coupe de Feu', null, 'J.K. Rowling',
                'Harry Potter a quatorze ans et entre en quatrième année au collège de Poudlard. Une grande nouvelle attend Harry, Ron et Hermione à leur arrivée.',
                'https://books.google.com/books/content?id=ox9BiuVKM1cC&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'Pottermore Publishing', '2015-12-08', 779, 'ebook',
                ['Juvenile Fiction / Fantasy & Magic', 'Young Adult Fiction / Fantasy / Wizards & Witches'],
            ],
            [
                '9780807060100', 'RMqMDQAAQBAJ',
                [['type' => 'ISBN_10', 'identifier' => '0807060100'], ['type' => 'ISBN_13', 'identifier' => '9780807060100']],
                'Man\'s Search for Meaning, Gift Edition', 'Gift Edition', 'Viktor E. Frankl',
                'The bestselling Holocaust memoir about finding purpose and strength in times of despair—selected as a Library of Congress "10 Most Influential Books in America"',
                'https://books.google.com/books/content?id=RMqMDQAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'Beacon Press', '2014-10-28', 200, 'paperback',
                ['Psychology / Movements / Existential', 'History / Modern / 20th Century / Holocaust'],
            ],
            [
                '9781781103074', 'PDcQCwAAQBAJ',
                [['type' => 'ISBN_10', 'identifier' => '1781103070'], ['type' => 'ISBN_13', 'identifier' => '9781781103074']],
                'Harry Potter e a Pedra Filosofal', null, 'J.K. Rowling',
                'Harry Potter não é um herói habitual. É apenas um miúdo magricela, míope e desajeitado com uma estranha cicatriz na testa.',
                'https://books.google.com/books/content?id=PDcQCwAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'Pottermore Publishing', '2015-12-08', 254, 'ebook',
                ['Juvenile Fiction / Fantasy & Magic', 'Young Adult Fiction / Fantasy / Wizards & Witches'],
            ],
            [
                '9788580410082', 'Pw-5t-RLpVsC',
                [['type' => 'ISBN_10', 'identifier' => '8580410088'], ['type' => 'ISBN_13', 'identifier' => '9788580410082']],
                'Não conte a ninguém', null, 'Harlan Coben',
                'Harlan Coben constrói uma história envolvente e eletrizante sobre perda e redenção, segredos inconfessáveis e um amor capaz de resistir a tudo.',
                'https://books.google.com/books/content?id=Pw-5t-RLpVsC&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'Editora Arqueiro', '2011-08-04', 256, 'ebook',
                ['Fiction / Thrillers / Suspense', 'Fiction / Mystery & Detective'],
            ],
            [
                '9724608158', 'N1ddSQAACAAJ',
                [['type' => 'ISBN_10', 'identifier' => '9724608158'], ['type' => 'ISBN_13', 'identifier' => '9789724608150']],
                'A profecia celestina: uma odisseia para o nosso tempo', null, 'James Redfield',
                'Romance espiritual sobre as nove visões que revelam o destino da humanidade.',
                'https://books.google.com/books/content?id=N1ddSQAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api',
                'Ed. Notícias', '2004-01-01', 301, 'paperback',
                ['Espiritualidade', 'Ficção'], 'enhanced',
            ],
            [
                '9781506721071', 'ZQMyEAAAQBAJ',
                [['type' => 'ISBN_10', 'identifier' => '1506721079'], ['type' => 'ISBN_13', 'identifier' => '9781506721071']],
                'Stranger Things and Dungeons & Dragons (Graphic Novel)', null, 'Jody Houser, Jim Zub',
                'Follow the crew from Hawkins, Indiana, as they discover the legendary monsters and epic adventures of the Dungeons & Dragons tabletop role-playing game together.',
                'https://books.google.com/books/content?id=ZQMyEAAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'Dark Horse Comics', '2021-07-06', 96, 'paperback',
                ['Comics & Graphic Novels / Media Tie-In', 'Comics & Graphic Novels / Fantasy'],
            ],
            [
                '9780385526951', '81nF3iyA640C',
                [['type' => 'ISBN_10', 'identifier' => '0385526954'], ['type' => 'ISBN_13', 'identifier' => '9780385526951']],
                'The Last Secret of Fatima', 'The Revelation of One of the Most Controversial Events in Catholic History', 'Cardinal Tarcisio Bertone',
                'With an introduction by Pope Benedict XVI and including information previously suppressed, the Vatican\'s Secretary of State, Cardinal Bertone, definitively reveals and explains one of the most controversial events in twentieth-century Catholicism.',
                'https://books.google.com/books/content?id=81nF3iyA640C&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'Crown Publishing Group', '2008-05-06', 192, 'ebook',
                ['Religion / Christianity / Catholic', 'Religion / Christian Church / History'],
            ],
            [
                '9781505108293', 'HuKNDAAAQBAJ',
                [['type' => 'ISBN_10', 'identifier' => '1505108292'], ['type' => 'ISBN_13', 'identifier' => '9781505108293']],
                'Fatima\'s Message for Our Times', null, 'Rev. Msgr. Joseph A. Cirrincione',
                'Summarizes the Fatima messages as a return to a life of prayer, to the traditional prayer life of the Catholic Church, especially to prayer before the Blessed Sacrament.',
                'https://books.google.com/books/content?id=HuKNDAAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api',
                'TAN Books', '2009-01-01', 72, 'ebook',
                ['Religion / Christian Theology / History', 'Religion / Christianity / Catholic'],
            ],
            [
                '8501404705', 'xyuyoAEACAAJ',
                [['type' => 'ISBN_10', 'identifier' => '8501404705'], ['type' => 'ISBN_13', 'identifier' => '9788501404701']],
                'Esquerda caviar', 'a hipocrisia dos artistas e intelectuais progressistas no Brasil e no mundo', 'Rodrigo Constantino',
                'Análise crítica sobre contradições de intelectuais progressistas.',
                null, 'Editora Record', '2014-01-01', 432, 'paperback',
                ['Política', 'Ensaio'], 'enhanced',
            ],
        ];
    }

    private function createBook(
        string $isbn,
        string $googleId,
        array $industryIdentifiers,
        string $title,
        ?string $subtitle,
        string $authors,
        string $description,
        ?string $thumbnail,
        string $publisher,
        string $publishedDate,
        int $pageCount,
        string $format,
        array $categories,
        string $infoQuality = 'complete'
    ): array {
        return [
            'id' => 'B-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
            'isbn' => $isbn,
            'google_id' => $googleId,
            'industry_identifiers' => json_encode($industryIdentifiers),
            'title' => $title,
            'subtitle' => $subtitle,
            'authors' => $authors,
            'description' => $description,
            'thumbnail' => $thumbnail,
            'language' => 'pt-BR',
            'publisher' => $publisher,
            'published_date' => $publishedDate,
            'page_count' => $pageCount,
            'format' => $format,
            'print_type' => 'BOOK',
            'categories' => json_encode($categories),
            'info_quality' => $infoQuality,
            'enriched_at' => now(),
        ];
    }
}
