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

- **Book**: Core book entity with Google Books integration
- **UserBook**: Many-to-many pivot tracking user's personal library
- **Author**: Book authors with merge capability for duplicates
- **RelatedBook**: Book recommendations and relationships
- **Showcase**: Featured books for public display

## Database Schema

```sql
Main Tables:
- users (authentication and profiles)
- books (book catalog with enriched data)
- user_books (personal library - many-to-many)
- authors (book authors)
- follows (user following system)
- reviews (book reviews and ratings)
- showcase (featured books)
- related_books (book recommendations)
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

# Firebase migration
docker exec livrolog-api php artisan firebase:discover
docker exec livrolog-api php artisan firebase:import --file=firebase-export.json
docker exec livrolog-api php artisan import:firestore-showcase
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

- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login with credentials
- `POST /api/auth/google` - Google OAuth sign-in
- `POST /api/auth/logout` - Logout current user
- `GET /api/auth/me` - Get authenticated user

### Books

- `GET /api/books` - List all books
- `POST /api/books` - Create new book
- `GET /api/books/{id}` - Get book details
- `PUT /api/books/{id}` - Update book
- `DELETE /api/books/{id}` - Delete book
- `GET /api/books/search?q={query}` - Search Google Books
- `POST /api/books/create-enriched` - Create with enriched data
- `POST /api/books/{id}/enrich` - Enrich existing book
- `POST /api/books/enrich-batch` - Batch enrichment

### User Library

- `GET /api/user/books` - Get authenticated user's library
- `DELETE /api/user/books/{book_id}` - Remove book from library
- `PATCH /api/user/books/{book_id}/read-date` - Update reading date

### Social Features

- `POST /api/users/{id}/follow` - Follow user
- `DELETE /api/users/{id}/unfollow` - Unfollow user
- `GET /api/users/{id}/followers` - Get followers list
- `GET /api/users/{id}/following` - Get following list
- `GET /api/users/{id}/follow-status` - Check follow status

### Reviews

- `GET /api/reviews` - List reviews
- `POST /api/reviews` - Create review
- `PUT /api/reviews/{id}` - Update review
- `DELETE /api/reviews/{id}` - Delete review
- `POST /api/reviews/{id}/helpful` - Mark as helpful

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

## Migration from Firebase

The project includes comprehensive Firebase/Firestore migration tools:

- Import users, books, and showcase data
- Preserve relationships and timestamps
- See FIREBASE_MIGRATION.md for details
