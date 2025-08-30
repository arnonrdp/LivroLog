<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class SocialMediaCrawlerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Detect social media crawlers
        if ($this->isSocialMediaCrawler($request)) {
            $path = $request->path();
            
            // Handle homepage
            if ($path === '/') {
                return $this->renderHomePage($request);
            }
            
            // Check if this is a user profile route
            // Match patterns like /username or /username/
            if (preg_match('/^([a-zA-Z0-9_\-\.]+)\/?$/', $path, $matches)) {
                $username = $matches[1];
                
                // Skip API routes and common frontend routes
                if (in_array($username, ['api', 'login', 'register', 'reset-password', 'documentation'])) {
                    return $next($request);
                }
                
                // Try to find the user
                $user = User::where('username', $username)->first();
                
                if ($user) {
                    return $this->renderUserProfilePage($user, $request);
                }
            }
        }
        
        return $next($request);
    }
    
    /**
     * Check if the request is from a social media crawler
     */
    private function isSocialMediaCrawler(Request $request): bool
    {
        $userAgent = strtolower($request->header('User-Agent', ''));
        
        $crawlers = [
            'facebookexternalhit',
            'twitterbot',
            'linkedinbot',
            'whatsapp',
            'telegrambot',
            'slackbot',
            'discordbot',
            'skypeuripreview',
            'applebot',
            'googlebot',
            'bingbot',
            'yahoo',
            'pinterest',
            'redditbot',
        ];
        
        foreach ($crawlers as $crawler) {
            if (strpos($userAgent, $crawler) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Render homepage with dynamic meta tags
     */
    private function renderHomePage(Request $request): \Illuminate\Http\Response
    {
        $currentUrl = $request->fullUrl();
        $imageUrl = config('app.url') . '/screenshot-web.jpg';
        
        $html = $this->generateHtmlWithMetaTags([
            'title' => 'LivroLog',
            'description' => 'A place for you to organize everything you\'ve read. Add your books and see what your friends are reading.',
            'og:type' => 'website',
            'og:url' => $currentUrl,
            'og:title' => 'LivroLog',
            'og:description' => 'A place for you to organize everything you\'ve read. Add your books and see what your friends are reading.',
            'og:image' => $imageUrl,
            'og:image:alt' => 'Homepage of the LivroLog website with a bookcase with several book covers',
            'og:site_name' => 'LivroLog',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => 'LivroLog',
            'twitter:description' => 'A place for you to organize everything you\'ve read. Add your books and see what your friends are reading.',
            'twitter:image' => $imageUrl,
        ]);
        
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
    
    /**
     * Render user profile page with dynamic meta tags
     */
    private function renderUserProfilePage(User $user, Request $request): \Illuminate\Http\Response
    {
        // Load user's books for count and shelf image
        $user->load(['books' => function ($query) {
            $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private');
            // Only count public books for meta description
            $query->wherePivot('is_private', false);
        }]);
        
        $booksCount = $user->books->count();
        $shelfName = $user->shelf_name ?: $user->display_name;
        $currentUrl = $request->fullUrl();
        $imageUrl = config('app.url') . "/api/users/{$user->id}/shelf-image";
        
        $description = $booksCount > 0 
            ? "Veja os {$booksCount} livros favoritos do {$user->display_name}" 
            : "Biblioteca do {$user->display_name} no LivroLog";
        
        $title = "{$shelfName} - LivroLog";
        
        $html = $this->generateHtmlWithMetaTags([
            'title' => $title,
            'description' => $description,
            'og:type' => 'profile',
            'og:url' => $currentUrl,
            'og:title' => $title,
            'og:description' => $description,
            'og:image' => $imageUrl,
            'og:image:alt' => "Estante de livros do {$user->display_name} no LivroLog",
            'og:site_name' => 'LivroLog',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $imageUrl,
            'profile:first_name' => $user->display_name,
            'profile:username' => $user->username,
        ]);
        
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
    
    /**
     * Generate HTML with dynamic meta tags
     */
    private function generateHtmlWithMetaTags(array $metaData): string
    {
        $metaTags = '';
        
        foreach ($metaData as $property => $content) {
            if ($property === 'title') {
                $metaTags .= "<title>{$content}</title>\n    ";
                continue;
            }
            
            if ($property === 'description') {
                $metaTags .= '<meta name="description" content="' . htmlspecialchars($content) . '">' . "\n    ";
                continue;
            }
            
            if (strpos($property, 'og:') === 0 || strpos($property, 'profile:') === 0) {
                $metaTags .= '<meta property="' . $property . '" content="' . htmlspecialchars($content) . '">' . "\n    ";
            } else {
                $metaTags .= '<meta name="' . $property . '" content="' . htmlspecialchars($content) . '">' . "\n    ";
            }
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Dynamic Meta Tags -->
    {$metaTags}
    
    <!-- Redirect to frontend -->
    <script>
        // Redirect to frontend for regular users
        if (!navigator.userAgent.match(/facebookexternalhit|twitterbot|linkedinbot|whatsapp|telegrambot|slackbot|discordbot|skypeuripreview|applebot|googlebot|bingbot|yahoo|pinterest|redditbot/i)) {
            window.location.href = '" . config('app.frontend_url') . "' + window.location.pathname;
        }
    </script>
    
    <!-- Fallback redirect -->
    <noscript>
        <meta http-equiv="refresh" content="0;url=" . config('app.frontend_url') . ">
    </noscript>
</head>
<body>
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h1>LivroLog</h1>
        <p>Redirecionando...</p>
        <p><a href="" . config('app.frontend_url') . "{$_SERVER['REQUEST_URI']}">Clique aqui se não for redirecionado automaticamente</a></p>
    </div>
</body>
</html>
HTML;
    }
}