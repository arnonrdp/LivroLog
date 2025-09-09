# Amazon Integration - LivroLog

## Current Status: ✅ PRODUCTION READY

### What's Working

- [x] Google Books (immediate) + Amazon (background) enrichment
- [x] Real Amazon ASIN discovery via HTML parsing
- [x] Frontend real-time polling (5s intervals, 2min timeout)
- [x] Affiliate monetization (`tag=livrolog01-20`)
- [x] Fallback search links when ASIN not found
- [x] Semantic UI: "Buy on Amazon" vs "Search on Amazon"

### What's Missing

- [ ] Amazon PA-API integration (when account approved)
- [x] Review section bug fix (user_id undefined in loadBookReviews)
- [ ] Multi-region support (US, UK, CA)

## Quick Reference

### Database Fields

```sql
-- books table
amazon_asin VARCHAR(20) NULL
asin_status ENUM('pending', 'processing', 'completed', 'failed')
asin_processed_at TIMESTAMP NULL
```

### Testing Results

- ✅ **"Harry Potter"** → ASIN `B01LQM96G4` found
- ✅ **"Into the Wild"** → ASIN `B000SEFNMS` found
- ✅ **"Na natureza selvagem"** → Search link generated
- ✅ **Real-time polling** → 6 API calls, auto-stops when complete

### Manual Reprocessing

```bash
# Fix failed books
docker exec livrolog-api php artisan tinker --execute="
\$book = App\Models\Book::find('BOOK-ID');
\$book->update(['asin_status' => 'pending']);
App\Jobs\EnrichBookWithAmazonJob::dispatch(\$book);
"
```

### Configuration

```env
AMAZON_ASSOCIATE_TAG=livrolog01-20
```

---

**Status**: Production Ready 🚀
