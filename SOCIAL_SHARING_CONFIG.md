# Social Media Sharing Configuration

This document explains how to configure the LivroLog application for proper social media sharing with dynamic Open Graph meta tags.

## Overview

The system uses server-side detection of social media crawlers to serve dynamic meta tags while redirecting regular users to the frontend. This ensures that shared links display personalized bookshelf information.

## Architecture

1. **Frontend (Vue.js)**: Detects crawlers via JavaScript and redirects to API
2. **Backend (Laravel)**: Generates dynamic HTML with user-specific meta tags
3. **Nginx**: Routes crawler requests to Laravel, regular users to frontend

## Environment Configuration

### Production Setup

For production deployment, update the following environment variables:

#### Frontend (.env)
```env
VITE_API_URL=https://api.livrolog.com
VITE_FRONTEND_URL=https://livrolog.com
```

#### Backend (.env)
```env
APP_URL=https://api.livrolog.com
APP_FRONTEND_URL=https://livrolog.com
```

#### Docker Compose (.env)
```env
APP_FRONTEND_URL=https://livrolog.com
```

### Development Setup

For local development (current configuration):

#### Frontend (.env)
```env
VITE_API_URL=http://localhost:8000
VITE_FRONTEND_URL=http://localhost:8001
```

#### Backend (.env)
```env
APP_URL=http://localhost:8000
APP_FRONTEND_URL=http://localhost:8001
```

## How It Works

### 1. Crawler Detection
The system detects the following social media crawlers:
- Facebook (`facebookexternalhit`)
- Twitter (`twitterbot`)
- LinkedIn (`linkedinbot`)
- WhatsApp (`whatsapp`)
- Telegram (`telegrambot`)
- Slack (`slackbot`)
- Discord (`discordbot`)
- And others...

### 2. Dynamic Meta Tags
For user profiles like `/arnon`, crawlers receive:
```html
<title>Arnon Rodrigues - LivroLog</title>
<meta property="og:title" content="Arnon Rodrigues - LivroLog">
<meta property="og:description" content="Veja os 87 livros favoritos do Arnon Rodrigues">
<meta property="og:image" content="https://api.livrolog.com/users/U-ABC-123/shelf-image">
<meta property="og:type" content="profile">
```

### 3. Shelf Image Generation
Each user gets a personalized bookshelf image at:
`https://api.livrolog.com/users/{userId}/shelf-image`

The image includes:
- User's display name
- Book count
- Up to 20 book covers in a grid layout
- LivroLog branding

## Files Modified

### Frontend
- `webapp/index.html`: Crawler detection and redirection script
- `webapp/vite.config.ts`: Environment variable injection
- `webapp/.env`: Configuration variables

### Backend
- `api/app/Http/Middleware/SocialMediaCrawlerMiddleware.php`: Crawler detection and HTML generation
- `api/app/Http/Controllers/Api/UserController.php`: Shelf image generation
- `api/routes/web.php`: User profile routes
- `api/bootstrap/app.php`: Middleware registration

### Infrastructure
- `api/docker/nginx/default.conf`: Nginx routing configuration
- `docker-compose.yml`: Service configuration

## Testing

### Test Crawler Detection
```bash
# Test Facebook crawler
curl -H "User-Agent: facebookexternalhit/1.1" "http://localhost:8000/arnon"

# Should return HTML with dynamic meta tags
```

### Test Regular User Redirection
```bash
# Test regular user
curl -v "http://localhost:8000/arnon"

# Should return 302 redirect to frontend
```

### Test Shelf Image Generation
```bash
# Test shelf image
curl "http://localhost:8000/api/users/U-UUGN-XZGT/shelf-image"

# Should return JPEG image
```

## Production Deployment

1. Update environment variables in production
2. Configure your web server (nginx/Apache) to handle the routing
3. Ensure SSL certificates are properly configured
4. Test with real social media platforms using their debugging tools:
   - Facebook: https://developers.facebook.com/tools/debug/
   - Twitter: https://cards-dev.twitter.com/validator
   - LinkedIn: https://www.linkedin.com/post-inspector/

## Troubleshooting

### Issue: Hard-coded domains
- **Problem**: URLs still show `dev` or `localhost`
- **Solution**: Update environment variables and restart services

### Issue: Crawlers not detecting
- **Problem**: Social media shows generic meta tags
- **Solution**: Check nginx configuration and crawler user agent detection

### Issue: Images not loading
- **Problem**: Shelf images return 404
- **Solution**: Verify user ID format and image generation endpoint

### Issue: Build errors (Rollup/Vite)
- **Problem**: Build fails with rollup module not found
- **Solution**: Delete `node_modules` and run `yarn install` again, or use `yarn dev` for development

## Notes

- The crawler detection script runs before any other JavaScript
- Environment variables are injected at build time for the frontend
- Nginx configuration supports specific user routes (add more as needed)
- The system gracefully degrades if shelf images can't be generated