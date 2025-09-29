#!/bin/bash

# Script to process Amazon enrichment for all books
# Usage: ./enrich_all_amazon.sh

echo "🚀 Starting Amazon enrichment batch process..."

# Navigate to project directory
cd "$(dirname "$0")"

# Check if Docker is running
if ! docker compose ps | grep -q "Up"; then
    echo "❌ Docker services are not running. Execute 'docker compose up -d' first."
    exit 1
fi

# Show initial statistics
echo "📊 Initial statistics:"
docker exec livrolog-api php artisan tinker --execute="
\$total = DB::table('books')->count();
\$withAsin = DB::table('books')->whereNotNull('amazon_asin')->count();
\$pending = DB::table('books')->where('asin_status', 'pending')->count();
echo 'Total books: ' . \$total . PHP_EOL;
echo 'With ASIN: ' . \$withAsin . PHP_EOL;
echo 'Pending: ' . \$pending . PHP_EOL;
"

# Dispatch jobs for all books that need enrichment
echo "⚡ Dispatching enrichment jobs..."
docker exec livrolog-api php artisan tinker --execute="
use App\Models\Book;
use App\Jobs\EnrichBookWithAmazonJob;

\$booksToEnrich = Book::whereNull('amazon_asin')
    ->whereNotIn('asin_status', ['processing', 'failed'])
    ->whereNotNull('isbn')
    ->get();

echo 'Found ' . \$booksToEnrich->count() . ' books to enrich' . PHP_EOL;

\$count = 0;
foreach (\$booksToEnrich as \$book) {
    \$book->update(['asin_status' => 'pending']);
    EnrichBookWithAmazonJob::dispatch(\$book);
    \$count++;
    if (\$count % 10 == 0) {
        echo 'Processed: ' . \$count . ' books' . PHP_EOL;
    }
}

echo 'Total jobs dispatched: ' . \$count . PHP_EOL;
"

# Process job queue
echo "🔄 Processing job queue (press Ctrl+C to stop)..."
echo "💡 Tip: Run 'docker exec livrolog-api php artisan queue:work' in another terminal for continuous processing"

# Process jobs for 30 seconds
timeout 30s docker exec livrolog-api php artisan queue:work --stop-when-empty --verbose

echo "✅ Script finished! Run again if necessary."
echo "📈 To check progress:"
echo "   docker exec livrolog-api php artisan tinker --execute=\"echo 'With ASIN: ' . DB::table('books')->whereNotNull('amazon_asin')->count() . '/' . DB::table('books')->count();\""