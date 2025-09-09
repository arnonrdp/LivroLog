# LivroLog Backend 🚀

Laravel 12 REST API backend for the LivroLog personal library management system.

## 🏗️ Technology Stack

-   **Laravel 12** with PHP 8.4
-   **MySQL 8.0** for primary database
-   **Redis 7.0** for caching and sessions
-   **Laravel Sanctum** for API authentication
-   **Google Books API** for book enrichment
-   **Swagger/OpenAPI** for API documentation
-   **Docker** for containerization

## 📋 Prerequisites

-   Docker & Docker Compose
-   Composer (for local development)
-   PHP 8.4+ (for local development)

## ⚡ Quick Start

### Using Docker (Recommended)

```bash
# Start API service
docker-compose up -d livrolog-api

# Install dependencies
docker exec livrolog-api composer install

# Setup application
docker exec livrolog-api php artisan key:generate
docker exec livrolog-api php artisan migrate
docker exec livrolog-api php artisan db:seed
```

The API will be available at: http://localhost:8000

### Local Development

```bash
cd api/

# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate
php artisan db:seed

# Start development server
php artisan serve
```

## 🔧 Development Commands

### Code Quality

```bash
# Format code with Laravel Pint
docker exec livrolog-api ./vendor/bin/pint

# Run tests
docker exec livrolog-api php artisan test

# Run specific test
docker exec livrolog-api php artisan test --filter=AuthTest
```

### Database Management

```bash
# Run migrations
docker exec livrolog-api php artisan migrate

# Fresh migration with seeding
docker exec livrolog-api php artisan migrate:fresh --seed

# Run seeders only
docker exec livrolog-api php artisan db:seed

# Create migration
docker exec livrolog-api php artisan make:migration create_example_table
```

### Queue Processing

```bash
# Process queued jobs
docker exec livrolog-api php artisan queue:work

# Clear failed jobs
docker exec livrolog-api php artisan queue:flush
```

### Book Enrichment

```bash
# Enrich all books with Google Books data
docker exec livrolog-api php artisan books:enrich

# Enrich specific book
docker exec livrolog-api php artisan books:enrich --book-id=B-3D6Y-9IO8

# Preview enrichment (dry run)
docker exec livrolog-api php artisan books:enrich --dry-run

# Force re-enrichment
docker exec livrolog-api php artisan books:enrich --force
```

## 🏗️ Project Structure

```
api/
├── app/
│   ├── Console/Commands/        # Artisan commands
│   ├── Http/
│   │   ├── Controllers/Api/     # API controllers
│   │   ├── Middleware/          # HTTP middleware
│   │   └── Requests/            # Form requests
│   ├── Models/                  # Eloquent models
│   ├── Services/                # Business logic services
│   └── Jobs/                    # Queued jobs
├── database/
│   ├── migrations/              # Database migrations
│   ├── seeders/                 # Database seeders
│   └── factories/               # Model factories
├── routes/
│   ├── api.php                  # API routes
│   └── web.php                  # Web routes
├── storage/
│   └── logs/                    # Application logs
└── tests/
    ├── Feature/                 # Feature tests
    └── Unit/                    # Unit tests
```

## 🔌 API Endpoints

### Authentication

-   `POST /auth/register` - Register new user
-   `POST /auth/login` - Login with credentials
-   `POST /auth/google` - Google OAuth sign-in
-   `POST /auth/logout` - Logout current user
-   `GET /auth/me` - Get authenticated user profile

### Books Management

-   `GET /books` - List all books with pagination
-   `POST /books` - Create new book
-   `GET /books/{id}` - Get book details
-   `PUT /books/{id}` - Update book information
-   `DELETE /books/{id}` - Delete book
-   `GET /books/search?q={query}` - Search Google Books API
-   `POST /books/create-enriched` - Create book with enriched data
-   `POST /books/{id}/enrich` - Enrich existing book data
-   `POST /books/enrich-batch` - Batch enrich multiple books

### User Library

-   `GET /user/books` - Get authenticated user's library
-   `POST /user/books` - Add book to user's library
-   `DELETE /user/books/{book_id}` - Remove book from library
-   `PATCH /user/books/{book_id}/read-date` - Update reading date

### Social Features

-   `POST /users/{id}/follow` - Follow another user
-   `DELETE /users/{id}/follow` - Unfollow user
-   `GET /users/{id}/followers` - Get user's followers
-   `GET /users/{id}/following` - Get users being followed

### Reviews & Ratings

-   `GET /reviews` - List book reviews
-   `POST /reviews` - Create book review
-   `PUT /reviews/{id}` - Update review
-   `DELETE /reviews/{id}` - Delete review
-   `POST /reviews/{id}/helpful` - Mark review as helpful

### Showcase

-   `GET /showcase` - Get featured books showcase
-   `POST /showcase` - Add book to showcase
-   `DELETE /showcase/{id}` - Remove from showcase

## 🗄️ Database Schema

### Core Models

```sql
-- Users and Authentication
users (id, name, email, google_id, avatar, created_at, updated_at)

-- Book Catalog
books (id, google_books_id, title, authors, isbn, thumbnail, pages, published_date, description, ...)
authors (id, name, created_at, updated_at)

-- User Library (Many-to-Many)
user_books (user_id, book_id, read_date, created_at, updated_at)

-- Social Features
follows (follower_id, following_id, created_at)
reviews (id, user_id, book_id, rating, review, helpful_count, created_at, updated_at)

-- Content Discovery
showcase (id, book_id, created_at, updated_at)
related_books (id, book_id, related_book_id, relationship_type, created_at, updated_at)
```

### Key Relationships

-   **User ↔ Books**: Many-to-many through `user_books`
-   **User ↔ Reviews**: One-to-many
-   **User ↔ Follows**: Many-to-many self-referential
-   **Books ↔ Authors**: Many-to-many
-   **Books ↔ Reviews**: One-to-many

## 🔒 Authentication & Security

### Laravel Sanctum

```php
// API authentication with Bearer tokens
'guards' => [
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```

### Google OAuth Integration

```bash
# Required environment variables
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_BOOKS_API_KEY=your-google-books-api-key
```

### Security Features

-   CORS configured for frontend domain
-   Rate limiting on API routes
-   Request validation and sanitization
-   Protected routes with Sanctum middleware
-   SQL injection protection via Eloquent ORM

## 📚 Google Books Integration

### Book Enrichment Service

The system automatically enriches book data using Google Books API:

```php
// Enrich single book
$bookService->enrichBook($book);

// Batch enrichment
$bookService->enrichBooks($books);
```

### Enriched Data Fields

-   **Basic Info**: Title, authors, ISBN, description
-   **Publishing**: Publisher, publication date, page count
-   **Physical**: Dimensions, format (paperback/hardcover)
-   **Content**: Categories, language, maturity rating
-   **Media**: High-resolution thumbnails and previews

### API Rate Limiting

-   Respects Google Books API quotas
-   Implements exponential backoff for rate limits
-   Queued processing for large batch operations

## 🧪 Testing

### Running Tests

```bash
# Run all tests
docker exec livrolog-api php artisan test

# Run specific test suite
docker exec livrolog-api php artisan test tests/Feature/AuthTest.php

# Run with coverage
docker exec livrolog-api php artisan test --coverage
```

### Test Structure

-   **Feature Tests**: End-to-end API testing
-   **Unit Tests**: Individual class and method testing
-   **Database**: Uses SQLite in-memory for fast test execution
-   **Factories**: Generate realistic test data
-   **Seeders**: Consistent test database state

## ⚙️ Environment Configuration

### Required Environment Variables

```env
# Application
APP_NAME=LivroLog
APP_ENV=local
APP_KEY=base64:generated-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=livrolog-mysql
DB_PORT=3306
DB_DATABASE=livrolog
DB_USERNAME=root
DB_PASSWORD=password

# Redis
REDIS_HOST=livrolog-redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Google Services
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_BOOKS_API_KEY=your-google-books-api-key

# Queue
QUEUE_CONNECTION=redis

# Mail (optional)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
```

## 📊 Performance & Monitoring

### Optimization Features

-   **Database Indexing**: Optimized queries with proper indexes
-   **Eager Loading**: Prevent N+1 query problems
-   **Redis Caching**: API response and query result caching
-   **Queue Processing**: Async handling of heavy operations
-   **Response Compression**: Gzip compression for API responses

### Monitoring & Debugging

```bash
# View application logs
docker exec livrolog-api tail -f storage/logs/laravel.log

# Monitor queue jobs
docker exec livrolog-api php artisan queue:monitor

# Database query debugging
docker exec livrolog-api php artisan telescope:install  # Optional
```

## 📖 API Documentation

### Swagger/OpenAPI

-   **Interactive Docs**: http://localhost:8000/documentation
-   **JSON Spec**: http://localhost:8000/docs/api.json
-   **Auto-generated**: From controller docblocks and request validation

### Postman Collection

API endpoints can be imported into Postman using the OpenAPI specification URL.

## 🚀 Deployment

### Artisan Commands

```bash
# Production optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Database migration
php artisan migrate --force
```

## 🔗 Related Documentation

-   [Frontend Documentation](../webapp/README.md)
-   [Project Overview](../README.md)
-   [CLAUDE.md](../CLAUDE.md) - Development guidelines
