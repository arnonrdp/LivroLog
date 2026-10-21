# Contributing to LivroLog

This document contains contribution guidelines and development standards for the LivroLog project.

## 📋 **Project Overview**

LivroLog is a personal library management system composed of:

- **Laravel API**: REST API backend with Docker
- **Vue.js + Quasar Frontend**: TypeScript SPA with Vite
- **Google Books API Integration**: For book search
- **Architecture**: Decoupled API + SPA

## 🛠 **Tech Stack**

### Backend

- **Laravel** with PHP
- **MySQL** for persistence
- **Redis** for cache/sessions
- **Docker** for containerization
- **Swagger** for API documentation

### Frontend

- **Vue.js 3** + **Composition API**
- **Quasar Framework** for UI/UX
- **TypeScript** with strict mode
- **Pinia** for state management
- **Vite** for build and development
- **Yarn** as package manager

## 📦 **Package Manager**

**Always use Yarn** (never npm):

```bash
# ✅ Correct
yarn install
yarn dev

# ❌ Avoid
npm install
```

Project uses Yarn 4.0 with Corepack for version consistency.

## 🏗 **Code Standards**

### **Pinia Stores**

#### **State**

- Always prefix with `_`
- Organize alphabetically

```typescript
state: () => ({
  _books: [] as Book[],
  _isLoading: false
}),
```

#### **Getters**

- No `get` prefix
- Organize alphabetically

```typescript
getters: {
  books: (state) => state._books,
  isLoading: (state) => state._isLoading
},
```

#### **Actions**

- Use `.then()`, `.catch()`, `.finally()` instead of `async/await`
- Manage loading states properly

```typescript
fetchBooks() {
  this._isLoading = true
  return api.get('/books')
    .then((response) => this.$patch({ _books: response.data }))
    .catch((error) => Notify.create({ message: error.response.data.message, type: 'negative' }))
    .finally(() => this._isLoading = false)
}
```

### **API Calls**

- Use centralized `utils/axios.ts`
- Follow "projeto modelo" pattern
- Avoid classes in stores
- Use interceptors for auth and error handling

### **TypeScript**

- **Strict mode enabled**
- Resolve ALL lint/type errors before commit
- Use explicit interfaces
- Avoid `any` - use specific types

### **Comments**

- **English only**
- Focus on "why", not "what"
- Document complex logic and business decisions

## 🗂 **File Structure**

### **Frontend**

```
src/
├── components/          # Reusable components
├── models/             # TypeScript interfaces
├── router/             # Route configuration
├── stores/             # Pinia stores
├── utils/              # Utilities (axios, helpers)
├── views/              # Main pages
└── i18n/               # Internationalization
```

### **Backend**

```
app/Http/Controllers/Api/    # API controllers
app/Models/                  # Eloquent models
routes/api.php              # API routes (/*)
```

## 🔄 **Development Workflow**

### **Git**

- Main branch: `main`
- Development: `dev`
- Features: `feature/feature-name`

### **Commits**

- Descriptive messages in English
- Use conventional commits when possible
- Atomic commits (one change per commit)

### **Pull Requests**

1. Fork the project
2. Create feature branch (`git checkout -b feature/new-feature`)
3. Commit changes (`git commit -am 'Add new feature'`)
4. Push to branch (`git push origin feature/new-feature`)
5. Open Pull Request

### **API First**

- Develop API endpoints first
- Document with Swagger
- Test with cURL/Postman before frontend

## 🧪 **Testing & Validation**

### **Frontend**

```bash
yarn type-check    # TypeScript validation
yarn build         # Production build
```

### **Backend**

```bash
docker exec livrolog-api php artisan test
```

## 🐳 **Docker**

The project runs with 5 containers:

- **nginx**: Reverse proxy (port 8000)
- **api**: Laravel API
- **database**: MySQL
- **redis**: Cache
- **frontend**: Node.js dev server (port 8001)

```bash
# Useful commands
docker ps                              # Container status
docker logs livrolog-api              # View logs
docker exec livrolog-api php artisan migrate
```

## 🔗 **Routes & Endpoints**

### **API (localhost:8000)**

- Base: `/`
- Auth: `/auth/{login,register,logout,me}`
- Books: `/books/`
- Documentation: `/documentation` (Swagger)

### **Frontend (localhost:8001)**

- Vue.js SPA with vue-router
- Bearer token authentication
- Auto-logout on 401 responses

## 🔐 **Authentication**

### **Flow**

1. Login/Register → receive `access_token`
2. Store token in `localStorage`
3. Axios interceptor adds `Bearer {token}`
4. Logout clears token and redirects

### **AuthResponse Structure**

```typescript
interface AuthResponse {
  user: User
  access_token: string
  token_type: string
}
```

## 🎨 **UI/UX Standards**

- **Quasar Framework** components
- Dark/Light mode support
- Mobile-first responsive design
- Use `$q.notify()` for user feedback

## 🚫 **What to Avoid**

- ❌ Using `npm` instead of `yarn`
- ❌ Classes and constructors in Pinia stores
- ❌ Unnecessary `try/catch` (use `.then().catch()`)
- ❌ `get` prefixes in getters
- ❌ States without `_` prefix
- ❌ Comments in Portuguese
- ❌ Direct commits to `main`
- ❌ Code without type checking

## 🎯 **Pre-Commit Checklist**

1. `yarn type-check` passes
1. Code formatted and linted
1. Comments in English
1. API documented (if new endpoint)
1. Manual functionality test
1. No forgotten `console.log`
1. Store states with `_` prefix
1. Getters organized alphabetically

---

## 📞 **Contact**

For questions about contributions or standards:

- **Author**: Arnon Rodrigues
- **Email**: arnonrdp@gmail.com
- **Website**: https://arnon.dev

---

_This document should be updated as the project evolves and new standards are established._
