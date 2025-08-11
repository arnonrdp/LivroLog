# LivroLog Frontend 🎨

Vue.js 3 frontend application for the LivroLog personal library management system.

## 🚀 Technology Stack

- **Vue.js 3** with Composition API
- **TypeScript** for type safety
- **Quasar Framework** for UI components
- **Pinia** for state management
- **Vue Router** for navigation
- **Vue I18n** for internationalization
- **Vite** as build tool
- **Yarn** as package manager

## 📋 Prerequisites

- Node.js 18+
- Yarn (required - do not use npm)
- Docker & Docker Compose (for backend services)

## ⚡ Quick Start

### Development Server

```bash
# Install dependencies (always use yarn)
yarn install

# Start development server
yarn dev
```

The application will be available at:

- **Development**: http://localhost:5173 (Vite dev server)
- **Docker**: http://localhost:8001 (when using docker-compose)

### Port Management

Check if ports are available before starting:

```bash
# Check Docker port
lsof -i:8001

# Check Yarn dev port
lsof -i:5173
```

## 🛠️ Development Commands

```bash
# Type checking
yarn type-check

# Linting
yarn lint

# Code formatting
yarn format

# Build for production
yarn build

# Preview production build
yarn preview
```

## 🏗️ Project Structure

```
webapp/
├── src/
│   ├── components/          # Reusable Vue components
│   ├── views/              # Page components
│   ├── stores/             # Pinia stores
│   ├── router/             # Vue Router configuration
│   ├── models/             # TypeScript interfaces
│   ├── utils/              # Utility functions
│   ├── i18n/               # Internationalization files
│   └── assets/             # Static assets
├── public/                 # Public static files
└── dist/                   # Built files (generated)
```

## 📱 Key Features

- **Personal Library Management** - Add, organize and track your books
- **Social Features** - Follow friends and see their reading activity
- **Google Books Integration** - Search and import book data
- **User Authentication** - JWT-based auth with Laravel Sanctum
- **Book Reviews** - Rate and review books
- **Responsive Design** - Mobile-first Quasar components
- **Internationalization** - Multi-language support

## 🎯 Development Standards

### Vue Component Organization

Always follow this order in `.vue` files:

1. **Imports** - External libraries, components, stores
2. **Props/Emits** - Component interface definitions
3. **Refs/Reactive** - State variables
4. **Computed** - Derived state
5. **Lifecycle hooks** - onMounted, onUnmounted, etc.
6. **Methods/Functions** - Component logic

### Async Operations

**Always use `.then()/.catch()` pattern** (avoid async/await):

```javascript
// ✅ Correct approach
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

### TypeScript Standards

- Strict mode enabled - resolve ALL type errors
- Use explicit interfaces, avoid `any`
- Define models in `src/models/`
- Prefer type inference when possible

## 🔌 API Integration

### Configuration

The frontend communicates with the Laravel backend via REST API:

- **Base URL**: Configured via `VITE_API_URL` environment variable
- **Authentication**: Bearer token with Laravel Sanctum
- **Client**: Centralized Axios instance in `utils/axios.ts`

### Authentication Flow

```javascript
// Auto-logout on 401 responses
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      authStore.logout()
      router.push('/login')
    }
    return Promise.reject(error)
  }
)
```

### Error Handling

- Global error handling with Quasar notifications
- Consistent error message display
- Automatic token refresh on expiration

## 🎨 UI/UX Guidelines

### Quasar Components

- Use Quasar components for consistent design
- Follow Material Design principles
- Implement responsive design patterns
- Use Quasar's built-in dark mode support

### Internationalization

```javascript
// Use i18n for all user-facing text
{
  {
    $t('common.save')
  }
}

// In stores and composables
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
```

## 📦 Environment Variables

Create `.env` file in the webapp directory:

```env
# API Configuration
VITE_API_URL=http://localhost:8000

# Google OAuth
VITE_GOOGLE_CLIENT_ID=your-google-client-id

# Development
VITE_DEV_MODE=true
```

## 🚀 Production Build

```bash
# Build for production
yarn build

# Test production build locally
yarn preview
```

The built files will be in the `dist/` directory.

## 🔧 Troubleshooting

### Common Issues

1. **Port conflicts**: Use `lsof` to check port availability
2. **Node version**: Ensure Node.js 18+ is installed
3. **Package manager**: Always use `yarn`, never `npm`
4. **Type errors**: Run `yarn type-check` to identify TypeScript issues
5. **API connection**: Verify `VITE_API_URL` in `.env` file

### Debug Mode

```bash
# Run with debug information
DEBUG=* yarn dev

# Vue DevTools
# Install Vue DevTools browser extension for component inspection
```

## 🔗 Related Documentation

- [Backend API Documentation](../api/README.md)
- [Project Overview](../README.md)
- [CLAUDE.md](../CLAUDE.md) - Development guidelines
