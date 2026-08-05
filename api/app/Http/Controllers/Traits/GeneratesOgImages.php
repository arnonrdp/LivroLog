<?php

namespace App\Http\Controllers\Traits;

/**
 * Shared building blocks for the Open Graph images rendered with GD
 * (user shelf cards and book cards).
 */
trait GeneratesOgImages
{
    /**
     * Ensure Roboto fonts are available locally for high-quality text rendering
     */
    private function ensureOgFont(): void
    {
        $publicDir = public_path('og/fonts');
        $storageDir = storage_path('app/fonts');
        if (! is_dir($publicDir)) {
            @mkdir($publicDir, 0775, true);
        }
        if (! is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }

        $fonts = [
            [
                'name' => 'Roboto-Bold.ttf',
                'urls' => [
                    'https://raw.githubusercontent.com/googlefonts/roboto/main/src/hinted/Roboto-Bold.ttf',
                    'https://raw.githubusercontent.com/google/fonts/main/apache/roboto/Roboto-Bold.ttf',
                ],
            ],
            [
                'name' => 'Roboto-Regular.ttf',
                'urls' => [
                    'https://raw.githubusercontent.com/googlefonts/roboto/main/src/hinted/Roboto-Regular.ttf',
                    'https://raw.githubusercontent.com/google/fonts/main/apache/roboto/Roboto-Regular.ttf',
                ],
            ],
        ];

        foreach ($fonts as $font) {
            $pubPath = $publicDir.DIRECTORY_SEPARATOR.$font['name'];
            $storPath = $storageDir.DIRECTORY_SEPARATOR.$font['name'];
            if (file_exists($pubPath) || file_exists($storPath)) {
                // Ensure both locations have a copy
                if (file_exists($pubPath) && ! file_exists($storPath)) {
                    @copy($pubPath, $storPath);
                } elseif (file_exists($storPath) && ! file_exists($pubPath)) {
                    @copy($storPath, $pubPath);
                }

                continue;
            }

            // Try downloading from known URLs
            foreach ($font['urls'] as $url) {
                try {
                    $data = @file_get_contents($url);
                    if ($data !== false && strlen($data) > 1000) { // crude sanity check
                        @file_put_contents($pubPath, $data);
                        @file_put_contents($storPath, $data);
                        break;
                    }
                } catch (\Throwable $e) {
                    // ignore and try next url
                }
            }
        }
    }

    /**
     * Resolve a TTF path, preferring the given weight. Returns null when no TTF
     * is available, which tells callers to fall back to GD's bitmap font.
     */
    private function getFontPath(string $prefer = 'Bold')
    {
        // Prefer Roboto (to match frontend), then Arial fallback
        $names = $prefer === 'Regular'
            ? ['Roboto-Regular.ttf', 'Roboto-Bold.ttf']
            : ['Roboto-Bold.ttf', 'Roboto-Regular.ttf'];

        foreach ($names as $name) {
            foreach ([public_path('og/fonts/'.$name), storage_path('app/fonts/'.$name)] as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        $arial = storage_path('app/fonts/arial.ttf');

        return file_exists($arial) ? $arial : null;
    }

    /**
     * Compute a renderer version based on last modified times of code and assets.
     * This automatically busts the cache when we change layout code, textures, or fonts.
     */
    private function getRendererVersion(): string
    {
        $files = [
            __FILE__,
            (new \ReflectionClass(static::class))->getFileName(),
            public_path('og/textures/shelfleft.jpg'),
            public_path('og/textures/shelfright.jpg'),
            public_path('og/textures/shelfcenter.jpg'),
            public_path('og/fonts/Roboto-Bold.ttf'),
            public_path('og/fonts/Roboto-Regular.ttf'),
            storage_path('app/fonts/Roboto-Bold.ttf'),
            storage_path('app/fonts/Roboto-Regular.ttf'),
            storage_path('app/fonts/arial.ttf'),
        ];
        $latest = 0;
        foreach ($files as $f) {
            $t = @filemtime($f);
            if ($t && $t > $latest) {
                $latest = $t;
            }
        }
        if (! $latest) {
            $latest = time();
        }

        return (string) $latest;
    }

    /**
     * Load image from URL with error handling
     */
    private function loadImageFromUrl($url)
    {
        try {
            if (! $this->isAllowedDomain($url)) {
                return null;
            }

            // Local cache for remote covers (TTL 7 days)
            $cacheDir = storage_path('app/cache/covers');
            if (! is_dir($cacheDir)) {
                @mkdir($cacheDir, 0775, true);
            }
            $hash = sha1($url);
            $cacheFile = $cacheDir.'/'.$hash.'.jpg';
            $ttl = 60 * 60 * 24 * 7; // 7 days

            if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
                $imageData = @file_get_contents($cacheFile);
            } else {
                $imageData = @file_get_contents($url);
                if ($imageData !== false) {
                    @file_put_contents($cacheFile, $imageData);
                }
            }
            if ($imageData === false) {
                return null;
            }

            $image = @imagecreatefromstring($imageData);

            return $image ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Allowlist of hosts we accept book covers from. Doubles as SSRF protection
     * when fetching remote images and as input validation when storing a
     * thumbnail URL, so both paths stay in sync.
     */
    private function isAllowedDomain(string $url): bool
    {
        $allowed = [
            'books.google.com',
            'books.googleapis.com',
            'lh3.googleusercontent.com',
            'ssl.gstatic.com',
            'covers.openlibrary.org',
            'm.media-amazon.com',
            'images-na.ssl-images-amazon.com',
            'images-eu.ssl-images-amazon.com',
        ];

        $parsed = parse_url($url);
        if (! isset($parsed['host']) || ! isset($parsed['scheme'])) {
            return false;
        }

        $host = strtolower($parsed['host']);
        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        foreach ($allowed as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wrap text to a pixel width using the TTF metrics, adding an ellipsis when
     * it overflows $maxLines.
     *
     * ponytail: does not hyphenate a single word wider than $maxWidth; add that
     * only if real titles show up clipped.
     *
     * @return array<int, string>
     */
    private function wrapTextToWidth(?string $font, float $size, string $text, int $maxWidth, int $maxLines = 3): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return [];
        }
        if (! $font) {
            return [mb_strimwidth($text, 0, 40 * $maxLines, '...')];
        }

        $lines = [];
        $current = '';
        foreach (explode(' ', $text) as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($size, 0, $font, $candidate);
            if ($current !== '' && ($box[2] - $box[0]) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        $lines[] = $current;

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = $lines[$maxLines - 1];
            $lines[$maxLines - 1] = rtrim(mb_substr($last, 0, max(1, mb_strlen($last) - 1))).'...';
        }

        return $lines;
    }
}
