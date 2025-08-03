# LivroLog 📚

Personal library management system with Google Books API integration.

## 🏗️ Architecture

The project is organized in a microservices architecture using Docker:

```
LivroLog/
├── api/              # Laravel 12 Backend + MySQL + Redis
├── webapp/           # Vue.js 3 + Quasar Frontend
├── docker-compose.yml # Services orchestration
└── docs/             # Project documentation
```

## 🚀 Quick Start

### Prerequisites

- Docker & Docker Compose
- Git

### Installation

1. **Clone the repository**

```bash
git clone https://github.com/arnonrdp/LivroLog.git
cd LivroLog
```

2. **Configure environment variables**

```bash
cp .env.example .env
# Edit the .env file as needed
```

3. **Start the services**

```bash
docker-compose up -d
```

4. **Configure the Laravel backend**

```bash
# Install dependencies
docker exec livrolog-api composer install

# Generate application key
docker exec livrolog-api php artisan key:generate

# Run migrations
docker exec livrolog-api php artisan migrate

# Enrich books with additional information (pages, format, etc.)
docker exec livrolog-api php artisan books:enrich

# Install Laravel Sanctum
docker exec livrolog-api php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

5. **Configure the Vue.js frontend**

```bash
# Install dependencies
docker exec livrolog-frontend yarn install

# Compile for development
docker exec livrolog-frontend yarn dev
```

## 📋 Services

### Backend API (Laravel 12)

- **URL**: http://localhost:8000
- **Documentation**: http://localhost:8000/documentation
- **Technologies**: PHP 8.4, Laravel 12, MySQL 8.0, Redis 7.0
- **Authentication**: Laravel Sanctum (Bearer Token)

### Frontend (Vue.js 3)

- **URL**: http://localhost:3000
- **Technologies**: Vue.js 3, Quasar Framework, TypeScript, Pinia

### Database

- **MySQL**: localhost:3306
- **Redis**: localhost:6379

## 🔧 API Endpoints

### Authentication

- `POST /api/auth/register` - Register user
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Get authenticated user data

### Books

- `GET /api/books` - List books
- `POST /api/books` - Create book
- `GET /api/books/{id}` - Get book details
- `PUT /api/books/{id}` - Update book
- `DELETE /api/books/{id}` - Delete book
- `GET /api/books/search?q={query}` - Search Google Books API
- `POST /api/books/create-enriched` - Create book with enriched information
- `POST /api/books/{id}/enrich` - Enrich existing book
- `POST /api/books/enrich-batch` - Enrich multiple books

### User Library

- `GET /api/users/{id}/books` - Get user books
- `POST /api/users/{id}/books` - Add book to library
- `DELETE /api/users/{id}/books/{book_id}` - Remove book from library
- `PATCH /api/users/{id}/books/{book_id}/read-date` - Update reading date

## 🗄️ Database

### Main Tables

- `users` - User data
- `books` - Book catalog
- `users_books` - Personal library (many-to-many)
- `related_books` - Related books

## 🛠️ Development

### Backend Structure (`/api`)

```
api/
├── app/
│   ├── Http/Controllers/Api/  # API Controllers
│   ├── Models/               # Eloquent Models
│   └── ...
├── database/
│   ├── migrations/           # Database migrations
│   └── ...
├── routes/
│   ├── api.php              # API routes
│   └── ...
└── ...
```

### Frontend Structure (`/webapp`)

```
webapp/
├── src/
│   ├── components/          # Vue components
│   ├── views/              # Pages/Views
│   ├── store/              # Pinia stores
│   ├── router/             # Route configuration
│   └── ...
└── ...
```

## 🔄 Firebase Migration

The project includes a comprehensive migration system from Firebase/Firestore to Laravel/MySQL:

### 🛠️ Migration Tools

- **Discovery**: `php artisan firebase:discover` - Find existing Firebase data
- **Import**: `php artisan firebase:import` - Migrate data (users, books, showcase)
- **Showcase**: `php artisan import:firestore-showcase` - Migrate showcase data only

### 📊 Migration Status

- ✅ **Infrastructure**: Docker, Laravel, MySQL configured
- ✅ **API**: REST endpoints implemented with authentication
- ✅ **Documentation**: Swagger/OpenAPI available
- ✅ **Migration Tools**: Complete commands for data import
- ✅ **Data Models**: All tables and relationships ready
- 🔄 **Frontend**: Migrating Firebase calls to REST API
- ⏳ **Data Migration**: Ready for your Firebase export

### 🚀 Quick Migration

```bash
# Discover existing Firebase data
php artisan firebase:discover

# Test migration (dry-run)
php artisan firebase:import --dry-run --file=firebase-export.json

# Execute full migration
php artisan firebase:import --clear --file=firebase-export.json

# Run demonstration
./demo-migration.sh
```

See [FIREBASE_MIGRATION.md](FIREBASE_MIGRATION.md) for complete migration guide.

## 📚 Book Enrichment

The system automatically enriches book information with additional details from Google Books API:

- 📖 **Page count** and **publication date**
- 🏷️ **Format** (paperback, hardcover, ebook)
- 📏 **Dimensions** and **categories**
- 📝 **Detailed descriptions** and **subtitles**

### Usage Examples

```bash
# Enrich all books with basic information
php artisan books:enrich

# Enrich specific books
php artisan books:enrich --book-id=B-3D6Y-9IO8

# Preview what would be enriched
php artisan books:enrich --dry-run
```

See [BOOK_ENRICHMENT.md](BOOK_ENRICHMENT.md) for complete enrichment guide.

## 📚 Documentation

- **API Documentation**: http://localhost:8000/documentation
- **OpenAPI Spec**: http://localhost:8000/docs/api.json

## 🤝 Contributing

1. Fork the project
2. Create a feature branch (`git checkout -b feature/new-feature`)
3. Commit your changes (`git commit -am 'Add new feature'`)
4. Push to the branch (`git push origin feature/new-feature`)
5. Open a Pull Request

## 📄 License

This project is under the MIT license. See the [LICENSE.md](LICENSE.md) file for details.

## 🏆 Technologies

### Backend

- **Laravel 12** - PHP Framework
- **MySQL 8.0** - Database
- **Redis 7.0** - Cache and sessions
- **Laravel Sanctum** - API Authentication
- **Nginx** - Web server

### Frontend

- **Vue.js 3** - JavaScript Framework
- **Quasar Framework** - UI Components
- **TypeScript** - Static typing
- **Pinia** - State Management
- **Vite** - Build tool

### DevOps

- **Docker** - Containerization
- **Docker Compose** - Orchestration
- **Nginx** - Reverse proxy

---

_Developed with ❤️ for book lovers_

<p align="center">
<a href="https://livrolog.com"><img src="https://img.shields.io/website?url=https%3A%2F%2Flivrolog.com" /></a>
<img src="https://img.shields.io/github/package-json/v/arnonrdp/LivroLog" />
<!-- <img alt="GitHub" src="https://img.shields.io/github/license/arnonrdp/LivroLog" /> -->
<img src="https://img.shields.io/github/repo-size/arnonrdp/LivroLog" />
<img alt="GitHub commit activity (branch)" src="https://img.shields.io/github/commit-activity/m/arnonrdp/LivroLog" />
<br />
<img src="https://img.shields.io/github/package-json/dependency-version/arnonrdp/LivroLog/pinia" />
<img src="https://img.shields.io/github/package-json/dependency-version/arnonrdp/LivroLog/quasar" />
<img src="https://img.shields.io/github/package-json/dependency-version/arnonrdp/LivroLog/vue" />
<img src="https://img.shields.io/github/package-json/dependency-version/arnonrdp/LivroLog/vue-router" />
<img src="https://img.shields.io/github/package-json/dependency-version/arnonrdp/LivroLog/vue-i18n" />
</p>

- Add all the books you've read to your shelf
- Follow your friends, see what each person's shelf looks like and find out what they just finished reading
- Download an image of your shelf and use it as a background in your video calls

<img src="./public/main.jpg" />
