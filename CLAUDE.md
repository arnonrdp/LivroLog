# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LivroLog is a personal library management system that allows users to organize their reading collection, follow other readers, and share book recommendations. It integrates with Google Books API for comprehensive book data and uses a modern microservices architecture.

**Tech Stack**:

- **Backend**: Laravel 12 (PHP 8.4) with MySQL and Redis
- **Frontend**: Vue.js 3 with TypeScript, Quasar UI, and Pinia state management
- **Infrastructure**: Docker Compose for local development
- **Authentication**: Laravel Sanctum with Bearer tokens and Google OAuth

## Architecture

```
LivroLog/
├── api/              # Laravel 12 Backend API
├── webapp/           # Vue.js 3 + Quasar Frontend
└── docker-compose.yml # Services orchestration
```

### Services

- **API**: http://localhost:8000
- **Frontend**: http://localhost:8001
- **MySQL**: localhost:3306
- **Redis**: localhost:6379
- **API Docs**: http://localhost:8000/documentation

## Core Domain Models

### User System

- **User**: Authenticated users with profiles, library, and social features
- **Follow**: Many-to-many relationship for user following system
- **Review**: User book reviews with ratings and helpfulness tracking

### Book System

- **Book**: Core book entity with Google Books integration and Amazon enrichment
- **UserBook**: Many-to-many pivot tracking user's personal library
- **Author**: Book authors with merge capability for duplicates
- **RelatedBook**: Book recommendations and relationships
- **Showcase**: Featured books for public display

### Book Enrichment System

- **UnifiedBookEnrichmentService**: Coordinates Google Books (sync) + Amazon (async) enrichment
- **EnrichBookWithAmazonJob**: Background job for Amazon ASIN discovery and affiliate link generation
- **AmazonLinkEnrichmentService**: Generates Amazon affiliate links (direct product or search links)
- **Google Books API**: Primary source for book metadata (synchronous)
- **Amazon Search**: Secondary source for ASINs and affiliate monetization (asynchronous)

## Database Schema

```sql
Main Tables:
- users (authentication and profiles)
- books (book catalog with enriched data + Amazon integration)
- user_books (personal library - many-to-many)
- authors (book authors)
- follows (user following system)
- reviews (book reviews and ratings)
- showcase (featured books)
- related_books (book recommendations)
- jobs (background job queue for Amazon enrichment)

Amazon Integration Fields in books table:
- amazon_asin (Amazon product identifier)
- asin_status (pending/processing/completed/failed)
- asin_processed_at (timestamp of last Amazon processing)
```

## Development Commands

### Backend (Laravel)

```bash
# Start development
docker exec livrolog-api php artisan serve

# Run tests
docker exec livrolog-api php artisan test
docker exec livrolog-api php artisan test --filter=FeatureName

# Code quality
docker exec livrolog-api ./vendor/bin/pint        # Format code
docker exec livrolog-api php artisan test         # Run tests

# Database
docker exec livrolog-api php artisan migrate
docker exec livrolog-api php artisan migrate:fresh --seed
docker exec livrolog-api php artisan db:seed

# Queue processing
docker exec livrolog-api php artisan queue:work

# Book enrichment (Google Books API)
docker exec livrolog-api php artisan books:enrich
docker exec livrolog-api php artisan books:enrich --book-id=B-3D6Y-9IO8
docker exec livrolog-api php artisan books:enrich --dry-run

# Amazon ASIN enrichment (automatic background processing)
# Jobs are dispatched automatically when books are created
# Manual reprocessing for failed books:
docker exec livrolog-api php artisan tinker --execute="
use App\Models\Book; use App\Jobs\EnrichBookWithAmazonJob;
\$book = Book::find('BOOK-ID-HERE');
\$book->update(['asin_status' => 'pending']);
EnrichBookWithAmazonJob::dispatch(\$book);
"
```

### Frontend (Vue.js)

```bash
# ALWAYS use yarn (never npm)
yarn install      # Install dependencies
yarn dev          # Start dev server

# Check ports before starting
lsof -i:8001      # Docker port
lsof -i:5173      # Yarn dev port

# Type checking and linting
yarn type-check
yarn lint
yarn format

# Build
yarn build
yarn preview
```

## Book Enrichment Flow

### Unified Book Enrichment Process

When a user adds a book to their library:

1. **Immediate Google Books Enrichment** (Synchronous)
   - Fetches complete book metadata (title, description, thumbnail, etc.)
   - User receives immediate response with full book data
   - Book stored with `enriched_at` timestamp

2. **Background Amazon Enrichment** (Asynchronous)
   - `EnrichBookWithAmazonJob` dispatched automatically
   - Status: `pending` → `processing` → `completed/failed`
   - Searches Amazon by ISBN first, then title + author
   - If ASIN found: generates direct product link
   - If not found: generates search link with affiliate tags

3. **Frontend Real-time Updates**
   - BookDialog polls for Amazon status changes every 5 seconds
   - Shows spinner during processing, active button when completed
   - Semantic button text: "Buy on Amazon" vs "Search on Amazon"
   - Auto-stops polling after 2 minutes or completion

### Amazon ASIN Discovery

The system uses a multi-layered approach:

1. **Primary**: Amazon Product Advertising API (when configured)
2. **Fallback**: HTML parsing of Amazon search results
3. **Graceful degradation**: Search links when ASIN not found

### Status Flow

```
Book Creation → asin_status: null
↓
Amazon Job Dispatched → asin_status: 'pending'
↓
Job Processing → asin_status: 'processing' 
↓
Success: asin_status: 'completed' + amazon_asin: 'B123456789'
Failure: asin_status: 'completed' + amazon_asin: null (fallback link)
Error: asin_status: 'failed'
```

## Frontend Development Standards

### Vue Component Organization

Follow this order in `.vue` files:

1. **Imports** - External libraries, components, stores
2. **Props/Emits** - Component interface definitions
3. **Refs/Reactive** - State variables
4. **Computed** - Derived state
5. **Lifecycle hooks** - onMounted, onUnmounted, etc.
6. **Methods/Functions** - Component logic

### Async Operations

**Always use `.then()/.catch()` pattern** (avoid async/await or try/catch):

```javascript
// ✅ Correct
function fetchBooks() {
  bookService
    .getAll()
    .then((response) => {
      books.value = response.data
    })
    .catch((error) => {
      console.error(error)
      notify({ message: 'Error loading books', type: 'negative' })
    })
    .finally(() => {
      isLoading.value = false
    })
}

// ❌ Avoid async/await and try/catch
```

### Pinia Store Conventions

- State properties prefixed with `_`: `_books`, `_isLoading`
- Getters without `get` prefix: `books`, `isLoading`
- Actions use `.then()/.catch()` pattern (not async/await)
- Organize properties alphabetically

Always prefer the pattern that uses "method name" + "summarized endpoint" as the function name. Examples:

- GET `/books` -> async getBooks()
- GET `/books/{id}` -> async getBook()
- PUT `/auth/me` -> async putMe()
- DELETE `/auth/me` -> async deleteMe()

### TypeScript

- Strict mode enabled - resolve ALL type errors
- Use explicit interfaces, avoid `any`
- Define models in `src/models/`

### API Integration

- Use centralized `utils/axios.ts`
- Bearer token authentication
- Auto-logout on 401 responses
- Error handling with Quasar notifications

## API Endpoints

### Authentication

- `POST /auth/register` - Register new user
- `POST /auth/login` - Login with credentials
- `POST /auth/google` - Google OAuth sign-in
- `POST /auth/logout` - Logout current user
- `GET /auth/me` - Get authenticated user

### Books

- `GET /books` - List all books
- `POST /books` - Create new book
- `GET /books/{id}` - Get book details
- `PUT /books/{id}` - Update book
- `DELETE /books/{id}` - Delete book
- `GET /books/search?q={query}` - Search Google Books
- `POST /books/create-enriched` - Create with enriched data
- `POST /books/{id}/enrich` - Enrich existing book
- `POST /books/enrich-batch` - Batch enrichment

### User Library

- `GET /user/books` - Get authenticated user's library
- `DELETE /user/books/{book_id}` - Remove book from library
- `PATCH /user/books/{book_id}/read-date` - Update reading date

### Social Features

- `POST /users/{id}/follow` - Follow user
- `DELETE /users/{id}/follow` - Unfollow user
- `GET /users/{id}/followers` - Get followers list
- `GET /users/{id}/following` - Get following list

### Reviews

- `GET /reviews` - List reviews
- `POST /reviews` - Create review
- `PUT /reviews/{id}` - Update review
- `DELETE /reviews/{id}` - Delete review
- `POST /reviews/{id}/helpful` - Mark as helpful

## Code Quality Standards

### Laravel/PHP

- Follow PSR-12 standards
- Use Laravel conventions (see CONTRIBUTING.md)
- Service pattern for complex business logic
- Repository pattern for data access when needed

### Vue.js/TypeScript

- Composition API with `<script setup>`
- Props with TypeScript interfaces
- Quasar components for UI
- i18n for all user-facing text

### Comments

- English only
- Focus on "why" not "what"
- Document complex business logic

## Testing Strategy

### Backend Testing

- Feature tests for API endpoints
- Unit tests for services and helpers
- Use factories for test data
- Database transactions for isolation

### Frontend Testing

- Component tests with Vitest
- E2E tests for critical flows
- Type checking as first line of defense

## Key Dependencies

### Backend

- Laravel 12.x with Sanctum
- Google Books API client
- Laravel Socialite for OAuth
- Spatie packages for various features

### Frontend

- Vue 3 with Composition API
- Quasar Framework 2.x
- Pinia for state management
- Axios for HTTP requests
- vue-i18n for internationalization

## Common Development Tasks

### Adding a New Feature

1. Create migration and model
2. Add API endpoint in `routes/api.php`
3. Create controller in `app/Http/Controllers/Api/`
4. Add TypeScript model in `webapp/src/models/`
5. Create/update Pinia store
6. Build Vue components
7. Add translations to locale files
8. Write tests

### Debugging

- Laravel logs: `docker exec livrolog-api tail -f storage/logs/laravel.log`
- Frontend: Browser DevTools + Vue DevTools
- API testing: Swagger UI at `/documentation`

## Environment Variables

### Backend (.env)

- `GOOGLE_BOOKS_API_KEY` - Required for book enrichment
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` - OAuth
- Database and Redis connection settings

### Frontend (.env)

- `VITE_API_URL` - Backend API URL
- `VITE_GOOGLE_CLIENT_ID` - Google OAuth client

## Production Deployment Notes

### Critical Server Configurations

These configurations are set directly on the server and must be maintained across deployments:

#### 1. MySQL/MariaDB Authentication (Development)
The development MySQL container requires `mysql_native_password` authentication:
```sql
ALTER USER "livrolog"@"%" IDENTIFIED WITH mysql_native_password BY "supersecret";
FLUSH PRIVILEGES;
```

#### 2. Apache CORS Headers (Production)
CORS is handled at the Apache level in `/opt/bitnami/apache/conf/bitnami/bitnami-ssl.conf`:
```apache
# Inside the api.livrolog.com VirtualHost block
Header always set Access-Control-Allow-Origin "https://livrolog.com https://www.livrolog.com"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "*"
```

#### 3. Environment Files
- **Development**: `/var/www/livrolog-dev/shared/.env.dev`
- **Production**: `/var/www/livrolog/shared/.env.prod`

**IMPORTANT**: `.env` files must contain ONLY environment variables, never shell commands or scripts.

Example of CORRECT format:
```bash
APP_NAME=LivroLog
APP_ENV=production
DB_CONNECTION=mysql
DB_HOST=mariadb
```

Example of INCORRECT format (will break Laravel):
```bash
echo "Setting up environment"  # ❌ NO shell commands
export DB_PASSWORD="..."       # ❌ NO export statements
if [ -z "$VAR" ]; then        # ❌ NO conditional logic
```

