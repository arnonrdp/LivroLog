<?php

namespace App\Services\Amazon;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonOAuthTokenManager
{
    private const TOKEN_CACHE_KEY = 'amazon_creators_access_token';

    private const TOKEN_TTL_SECONDS = 3300; // 55 minutes (5 min buffer before 1 hour expiry)

    private const SCOPE = 'creatorsapi/default';

    // Token endpoints by credential version (region)
    private const TOKEN_ENDPOINTS = [
        '2.1' => 'https://creatorsapi.auth.us-east-1.amazoncognito.com/oauth2/token', // NA
        '2.2' => 'https://creatorsapi.auth.eu-south-2.amazoncognito.com/oauth2/token', // EU
        '2.3' => 'https://creatorsapi.auth.us-west-2.amazoncognito.com/oauth2/token', // FE
    ];

    private string $credentialId;

    private string $credentialSecret;

    private string $version;

    private string $tokenEndpoint;

    public function __construct()
    {
        $this->credentialId = config('services.amazon.creators_api.credential_id', '');
        $this->credentialSecret = config('services.amazon.creators_api.credential_secret', '');
        $this->version = config('services.amazon.creators_api.api_version', '2.1');

        // Allow explicit configuration of token endpoint, falling back to version-based defaults
        $configuredEndpoint = config('services.amazon.creators_api.token_endpoint');

        if (! empty($configuredEndpoint)) {
            $this->tokenEndpoint = $configuredEndpoint;
        } else {
            $this->tokenEndpoint = self::TOKEN_ENDPOINTS[$this->version] ?? self::TOKEN_ENDPOINTS['2.1'];
        }
    }

    /**
     * Get the token endpoint URL based on credential version or config override
     */
    private function getTokenEndpoint(): string
    {
        return $this->tokenEndpoint;
    }

    /**
     * Get a valid access token, either from cache or by requesting a new one
     *
     * @throws \Exception If token cannot be obtained
     */
    public function getAccessToken(): string
    {
        // Try to get cached token first
        $cachedToken = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cachedToken) {
            return $cachedToken;
        }

        // Request new token
        return $this->requestNewToken();
    }

    /**
     * Request a new OAuth token from Amazon Cognito
     *
     * @throws \Exception If token request fails
     */
    private function requestNewToken(): string
    {
        if (empty($this->credentialId) || empty($this->credentialSecret)) {
            throw new \Exception('Amazon Creators API credentials not configured');
        }

        $tokenEndpoint = $this->getTokenEndpoint();

        Log::info('Amazon OAuth: Requesting new access token', [
            'endpoint' => $tokenEndpoint,
            'version' => $this->version,
        ]);

        $response = Http::asForm()
            ->timeout(30)
            ->retry(3, 1000)
            ->post($tokenEndpoint, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->credentialId,
                'client_secret' => $this->credentialSecret,
                'scope' => self::SCOPE,
            ]);

        if (! $response->successful()) {
            $error = $response->json('error_description') ?? $response->body();
            Log::error('Amazon OAuth: Failed to obtain token', [
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new \Exception('Failed to obtain Amazon OAuth token: '.$error);
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;

        if (! $accessToken) {
            throw new \Exception('Amazon OAuth response missing access_token');
        }

        // Cache the token
        $expiresIn = $data['expires_in'] ?? 3600;
        $cacheTtl = min($expiresIn - 300, self::TOKEN_TTL_SECONDS); // Use smaller of buffer or default

        Cache::put(self::TOKEN_CACHE_KEY, $accessToken, $cacheTtl);

        Log::info('Amazon OAuth: Token obtained and cached', [
            'expires_in' => $expiresIn,
            'cache_ttl' => $cacheTtl,
        ]);

        return $accessToken;
    }

    /**
     * Invalidate the cached token (useful for handling 401 errors)
     */
    public function invalidateToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
        Log::info('Amazon OAuth: Token cache invalidated');
    }

    /**
     * Check if credentials are configured
     */
    public function hasCredentials(): bool
    {
        return ! empty($this->credentialId) && ! empty($this->credentialSecret);
    }
}
