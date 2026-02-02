<?php

namespace App\Services\Amazon;

use App\Contracts\BookSearchProvider;
use App\Services\Providers\AmazonBooksProvider;
use App\Services\Providers\AmazonCreatorsProvider;

/**
 * Factory for selecting the appropriate Amazon provider based on configuration
 *
 * Supports switching between:
 * - PA-API 5.0 (legacy, uses AWS Signature authentication)
 * - Creators API (new, uses OAuth 2.0 authentication)
 */
class AmazonProviderFactory
{
    private AmazonCreatorsProvider $creatorsProvider;

    private AmazonBooksProvider $paApiProvider;

    public function __construct(
        AmazonCreatorsProvider $creatorsProvider,
        AmazonBooksProvider $paApiProvider
    ) {
        $this->creatorsProvider = $creatorsProvider;
        $this->paApiProvider = $paApiProvider;
    }

    /**
     * Create the configured Amazon provider
     */
    public function create(): BookSearchProvider
    {
        $provider = config('services.amazon.provider', 'creators');

        if ($provider === 'creators' && $this->creatorsProvider->isEnabled()) {
            return $this->creatorsProvider;
        }

        if ($provider === 'pa-api' && $this->paApiProvider->isEnabled()) {
            return $this->paApiProvider;
        }

        // Fallback: try creators first, then pa-api
        if ($this->creatorsProvider->isEnabled()) {
            return $this->creatorsProvider;
        }

        return $this->paApiProvider;
    }

    /**
     * Get the Creators API provider directly
     */
    public function getCreatorsProvider(): AmazonCreatorsProvider
    {
        return $this->creatorsProvider;
    }

    /**
     * Get the PA-API provider directly
     */
    public function getPaApiProvider(): AmazonBooksProvider
    {
        return $this->paApiProvider;
    }

    /**
     * Check which provider is currently configured
     */
    public function getConfiguredProviderName(): string
    {
        return config('services.amazon.provider', 'creators');
    }

    /**
     * Check if any Amazon provider is available
     */
    public function isAnyProviderEnabled(): bool
    {
        return $this->creatorsProvider->isEnabled() || $this->paApiProvider->isEnabled();
    }
}
