# LivroLog 📚

Personal library management system that allows users to organize their reading collection, follow other readers, and share book recommendations with Google Books API integration.

<p align="center">
<a href="https://livrolog.com"><img src="https://img.shields.io/website?url=https%3A%2F%2Flivrolog.com" /></a>
<img src="https://img.shields.io/github/package-json/v/arnonrdp/LivroLog?filename=webapp%2Fpackage.json" />
<img src="https://img.shields.io/github/repo-size/arnonrdp/LivroLog" />
<img alt="GitHub commit activity (branch)" src="https://img.shields.io/github/commit-activity/m/arnonrdp/LivroLog" />
<br />
<img src="https://img.shields.io/github/package-json/dependency-version/arnonrdp/LivroLog/pinia?filename=webapp%2Fpackage.json" />
<img src="https://img.shields.io/github/package-json/dependency-version/arnonrdp/LivroLog/quasar?filename=webapp%2Fpackage.json" />
<img src="https://img.shields.io/github/package-json/dependency-version/arnonrdp/LivroLog/vue?filename=webapp%2Fpackage.json" />
<img src="https://img.shields.io/github/package-json/dependency-version/arnonrdp/LivroLog/vue-router?filename=webapp%2Fpackage.json" />
<img src="https://img.shields.io/github/package-json/dependency-version/arnonrdp/LivroLog/vue-i18n?filename=webapp%2Fpackage.json" />
</p>

- Add all the books you've read to your shelf
- Follow your friends, see what each person's shelf looks like and find out what they just finished reading
- Download an image of your shelf and use it as a background in your video calls

<img src="./webapp/public/main.jpg" />

_Developed with ❤️ for book lovers_

## 🏗️ Architecture

```
LivroLog/
├── api/                # Laravel 12 Backend + MySQL + Redis
├── webapp/             # Vue.js 3 + Quasar Frontend
└── docker-compose.yml  # Services orchestration
```

## 🚀 Quick Start

1. **Clone and setup environment**

```bash
git clone https://github.com/arnonrdp/LivroLog.git
cd LivroLog
cp .env.example .env
```

2. **Start services**

```bash
docker-compose up -d
```

3. **Setup backend**

```bash
docker exec livrolog-api composer install
docker exec livrolog-api php artisan key:generate
docker exec livrolog-api php artisan migrate
```

4. **Setup frontend**

```bash
docker exec livrolog-frontend yarn install
docker exec livrolog-frontend yarn dev
```

## 📋 Services

- **Backend API**: http://localhost:8000 ([Documentation](http://localhost:8000/documentation))
- **Frontend**: http://localhost:3000
- **MySQL**: localhost:3306
- **Redis**: localhost:6379

## 📚 Documentation

- **[📁 Backend API Documentation](./api/README.md)** - Laravel backend, database, and API details
- **[🎨 Frontend Documentation](./webapp/README.md)** - Vue.js frontend and UI components

## 🏆 Tech Stack

**Backend**: Laravel 12, PHP 8.4, MySQL 8.0, Redis 7.0, Laravel Sanctum  
**Frontend**: Vue.js 3, Quasar Framework, TypeScript, Pinia  
**Infrastructure**: Docker, Docker Compose, Nginx

## 🤝 Contributing

1. Fork the project
2. Create feature branch (`git checkout -b feature/new-feature`)
3. Commit changes (`git commit -am 'Add new feature'`)
4. Push to branch (`git push origin feature/new-feature`)
5. Open Pull Request
