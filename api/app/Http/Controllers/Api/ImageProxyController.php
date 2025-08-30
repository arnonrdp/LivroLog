<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ImageProxyController extends Controller
{
    private const CACHE_TTL = 3600; // 1 hour
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB
    private const TIMEOUT = 10; // 10 seconds
    private const MAX_RETRIES = 3;

    private const ALLOWED_DOMAINS = [
        'books.google.com',
        'books.googleapis.com',
        'lh3.googleusercontent.com',
        'ssl.gstatic.com'
    ];

    private const ALLOWED_CONTENT_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/gif'
    ];

    public function proxy(Request $request): Response|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid URL provided',
                'details' => $validator->errors()
            ], 400);
        }

        $url = $request->query('url');

        // Validate domain
        if (!$this->isAllowedDomain($url)) {
            return response()->json([
                'error' => 'Domain not allowed',
                'allowed_domains' => self::ALLOWED_DOMAINS
            ], 403);
        }

        // Try to get from cache first
        $cacheKey = 'image_proxy_' . md5($url);
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            $content = base64_decode($cachedData['content']);
            return response($content)
                ->header('Content-Type', $cachedData['content_type'])
                ->header('Content-Length', strlen($content))
                ->header('Cache-Control', 'public, max-age=3600')
                ->header('X-Proxy-Cache', 'HIT');
        }

        // Download image with retry logic
        $imageData = $this->downloadImageWithRetry($url);

        if (!$imageData) {
            return response()->json([
                'error' => 'Failed to download image after retries',
                'url' => $url
            ], 502);
        }

        // Validate content type
        $contentType = $imageData['content_type'];
        if (!in_array($contentType, self::ALLOWED_CONTENT_TYPES)) {
            return response()->json([
                'error' => 'Invalid content type',
                'received' => $contentType,
                'allowed' => self::ALLOWED_CONTENT_TYPES
            ], 400);
        }

        // Validate file size
        $contentLength = strlen($imageData['content']);
        if ($contentLength > self::MAX_IMAGE_SIZE) {
            return response()->json([
                'error' => 'Image too large',
                'size' => $contentLength,
                'max_allowed' => self::MAX_IMAGE_SIZE
            ], 400);
        }

        // Cache the successful result (encode binary data as base64 for safe storage)
        Cache::put($cacheKey, [
            'content' => base64_encode($imageData['content']),
            'content_type' => $contentType,
            'cached_at' => now()->toDateTimeString()
        ], self::CACHE_TTL);

        Log::info('Image proxy served', [
            'url' => $url,
            'size' => $contentLength,
            'content_type' => $contentType,
            'cached' => false
        ]);

        return response($imageData['content'])
            ->header('Content-Type', $contentType)
            ->header('Content-Length', $contentLength)
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Proxy-Cache', 'MISS');
    }

    private function isAllowedDomain(string $url): bool
    {
        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['host'])) {
            return false;
        }

        $host = strtolower($parsedUrl['host']);

        foreach (self::ALLOWED_DOMAINS as $allowedDomain) {
            // Exact match or subdomain match
            if ($host === $allowedDomain || str_ends_with($host, '.' . $allowedDomain)) {
                return true;
            }
        }

        return false;
    }

    private function downloadImageWithRetry(string $url): ?array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                Log::debug("Image proxy attempt {$attempt}/{" . self::MAX_RETRIES . "}", ['url' => $url]);

                $response = Http::timeout(self::TIMEOUT)
                    ->withHeaders([
                        'User-Agent' => 'LivroLog Image Proxy/1.0',
                        'Accept' => implode(', ', self::ALLOWED_CONTENT_TYPES),
                        'Accept-Encoding' => 'gzip, deflate',
                        'Connection' => 'close'
                    ])
                    ->get($url);

                if (!$response->successful()) {
                    Log::warning("Image proxy HTTP error on attempt {$attempt}", [
                        'url' => $url,
                        'status' => $response->status(),
                        'headers' => $response->headers()
                    ]);

                    // Don't retry on 4xx errors (client errors)
                    if ($response->status() >= 400 && $response->status() < 500) {
                        Log::error('Image proxy client error - not retrying', [
                            'url' => $url,
                            'status' => $response->status()
                        ]);
                        return null;
                    }

                    throw new \Exception("HTTP {$response->status()}");
                }

                $contentType = $response->header('content-type');
                if (!$contentType) {
                    // Try to detect content type from content
                    $contentType = $this->detectContentType($response->body());
                }

                $content = $response->body();
                if (empty($content)) {
                    throw new \Exception('Empty response body');
                }

                return [
                    'content' => $content,
                    'content_type' => $contentType
                ];

            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("Image proxy attempt {$attempt} failed", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'max_attempts' => self::MAX_RETRIES
                ]);

                // Wait before retry (exponential backoff)
                if ($attempt < self::MAX_RETRIES) {
                    $waitTime = pow(2, $attempt - 1); // 1s, 2s, 4s
                    sleep($waitTime);
                }
            }
        }

        Log::error('Image proxy failed after all retries', [
            'url' => $url,
            'attempts' => self::MAX_RETRIES,
            'last_error' => $lastException?->getMessage()
        ]);

        return null;
    }

    private function detectContentType(string $content): string
    {
        // Basic content type detection based on file signature
        $signatures = [
            "\xFF\xD8\xFF" => 'image/jpeg',
            "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" => 'image/png',
            "GIF87a" => 'image/gif',
            "GIF89a" => 'image/gif',
            "RIFF" => 'image/webp' // Simplified WebP detection
        ];

        foreach ($signatures as $signature => $contentType) {
            if (str_starts_with($content, $signature)) {
                return $contentType;
            }
        }

        // Default fallback
        return 'image/jpeg';
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'status' => 'active',
            'cache_ttl' => self::CACHE_TTL,
            'max_image_size' => self::MAX_IMAGE_SIZE,
            'timeout' => self::TIMEOUT,
            'max_retries' => self::MAX_RETRIES,
            'allowed_domains' => self::ALLOWED_DOMAINS,
            'allowed_content_types' => self::ALLOWED_CONTENT_TYPES,
            'proxy_url_format' => '/image-proxy?url={encoded_image_url}'
        ]);
    }
}
