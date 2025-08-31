<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        // Detect social media crawlers or forced OG via query (?og=1)
        $forceOg = $request->boolean('og');
        $isCrawler = $this->isSocialMediaCrawler($request);
        if ($forceOg || $isCrawler) {
            $path = $request->path();

            // Handle homepage
            if ($path === '/') {
                return $this->renderHomePage($request, $forceOg);
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
                    return $this->renderUserProfilePage($user, $request, $forceOg);
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
            'facebookexternalhit', 'facebookcatalog',
            'twitterbot',
            'linkedinbot',
            'whatsapp',
            'telegrambot', 'telegram',
            'slackbot', 'slack-imgproxy', 'slackbot-linkexpanding',
            'discordbot',
            'skypeuripreview',
            'applebot',
            'googlebot', 'google-inspectiontool', 'apis-google',
            'bingbot',
            'yahoo',
            'pinterestbot', 'pinterest',
            'redditbot',
            'embedly', 'iframely', 'opengraph',
            'vkshare', 'qwantify', 'bitlybot', 'bufferbot',
            'duckduckbot', 'baiduspider', 'yandexbot',
            'lighthouse', 'pagespeed'
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
    private function renderHomePage(Request $request, bool $forceOg): \Illuminate\Http\Response
    {
        $frontend = config('app.frontend_url');
        $currentUrl = $frontend; // canonical to frontend
        $imageUrl = rtrim($frontend, '/') . '/screenshot-web.jpg';

        // Basic i18n based on Accept-Language
        $lang = strtolower($request->header('Accept-Language', ''));
        $isPt = str_contains($lang, 'pt');
        $title = 'LivroLog';
        $description = $isPt
            ? 'O lugar perfeito para catalogar seus livros. Adicione sua estante e veja o que seus amigos estão lendo.'
            : "A place for you to organize everything you've read. Add your books and see what your friends are reading.";

        $html = $this->generateHtmlWithMetaTags([
            'title' => $title,
            'description' => $description,
            'og:type' => 'website',
            'og:url' => $currentUrl,
            'og:title' => $title,
            'og:description' => $description,
            'og:image' => $imageUrl,
            'og:image:alt' => 'Homepage of the LivroLog website with a bookcase with several book covers',
            'og:site_name' => 'LivroLog',
            'og:locale' => $isPt ? 'pt_BR' : 'en_US',
            'og:image:width' => '1200',
            'og:image:height' => '630',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $imageUrl,
        ], $forceOg);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Render user profile page with dynamic meta tags
     */
    private function renderUserProfilePage(User $user, Request $request, bool $forceOg): \Illuminate\Http\Response
    {
        // Load user's books for count and shelf image
        $user->load(['books' => function ($query) {
            $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private');
            // Only count public books for meta description
            $query->wherePivot('is_private', false);
        }]);

        $booksCount = $user->books->count();
        // Sanitize user-controlled fields to prevent XSS in reflected HTML
        $rawShelfName = $user->shelf_name ?: $user->display_name;
        $shelfName = trim(strip_tags((string) $rawShelfName));
        $safeDisplayName = trim(strip_tags((string) $user->display_name));
        // Canonical URL to frontend profile
        $frontend = rtrim(config('app.frontend_url'), '/');
        $currentUrl = $frontend . '/' . rawurlencode($user->username);
        // Version for cache-busting
        $versionTs = DB::table('users_books')
            ->where('user_id', $user->id)
            ->max('updated_at');
        $version = $versionTs ? (is_string($versionTs) ? (string) strtotime($versionTs) : (string) strtotime((string) $versionTs)) : (string) time();
        // Image served by API without /api prefix, with version.
        // Prefer current request host so that proxies/CDNs (dev.livrolog.com) generate same-host URLs.
        $hostBase = rtrim(($request->getSchemeAndHttpHost() ?: config('app.url')), '/');
        $imageUrl = $hostBase . "/users/{$user->id}/shelf-image?v={$version}";

        // i18n based on Accept-Language
        $lang = strtolower($request->header('Accept-Language', ''));
        $isPt = str_contains($lang, 'pt');

        $description = $booksCount > 0
            ? ($isPt ? "Veja os {$booksCount} livros favoritos de {$safeDisplayName}" : "See {$safeDisplayName}'s top {$booksCount} books")
            : ($isPt ? "Estante de {$safeDisplayName} no LivroLog" : "{$safeDisplayName}'s bookshelf on LivroLog");

        $title = "{$shelfName} - LivroLog";

        $html = $this->generateHtmlWithMetaTags([
            'title' => $title,
            'description' => $description,
            'og:type' => 'profile',
            'og:url' => $currentUrl,
            'og:title' => $title,
            'og:description' => $description,
            'og:image' => $imageUrl,
            'og:image:alt' => $isPt ? "Estante de livros de {$safeDisplayName} no LivroLog" : "{$safeDisplayName}'s bookshelf on LivroLog",
            'og:site_name' => 'LivroLog',
            'og:locale' => $isPt ? 'pt_BR' : 'en_US',
            'og:image:width' => '1200',
            'og:image:height' => '630',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $imageUrl,
            'profile:first_name' => $safeDisplayName,
            'profile:username' => $user->username,
        ], $forceOg);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Generate HTML with dynamic meta tags
     */
    private function generateHtmlWithMetaTags(array $metaData, bool $forceOg = false): string
    {
        $metaTags = '';

        foreach ($metaData as $property => $content) {
            if ($property === 'title') {
                $metaTags .= '<title>' . htmlspecialchars($content) . '</title>' . "\n    ";
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
        $frontendUrl = config('app.frontend_url');
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $safeFrontendUrl = htmlspecialchars($frontendUrl, ENT_QUOTES);
        $safeRequestUri = htmlspecialchars($requestUri, ENT_QUOTES);

        $includeClientRedirect = !$forceOg; // do not include client-side redirect when forcing OG

        $redirectScript = '';
        if ($includeClientRedirect) {
            $redirectScript = <<<JS
    <script>
        // Redirect to frontend for regular users
        if (!navigator.userAgent.match(/facebookexternalhit|twitterbot|linkedinbot|whatsapp|telegrambot|slackbot|discordbot|skypeuripreview|applebot|googlebot|bingbot|yahoo|pinterest|redditbot/i)) {
            window.location.href = '{$safeFrontendUrl}' + window.location.pathname + window.location.search;
        }
    </script>
JS;
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
    {$redirectScript}

    <!-- Fallback redirect -->
    <noscript>
        <meta http-equiv="refresh" content="0;url={$safeFrontendUrl}{$safeRequestUri}">
    </noscript>
</head>
<body>
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h1>LivroLog</h1>
        <p>Redirecionando...</p>
        <p><a href="{$safeFrontendUrl}{$safeRequestUri}">Clique aqui se não for redirecionado automaticamente</a></p>
    </div>
</body>
</html>
HTML;
    }
}
