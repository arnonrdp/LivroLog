<?php

namespace App\Contracts;

interface BookSearchProvider
{
    /**
     * Search for books using various search criteria
     *
     * @param  string  $query  Search query (ISBN, title, or title+author)
     * @param  array  $options  Additional search options
     * @return array Search results with standardized format
     */
    public function search(string $query, array $options = []): array;

    /**
     * Get the provider name for logging and identification
     *
     * @return string Provider name
     */
    public function getName(): string;

    /**
     * Check if the provider is enabled and configured
     *
     * @return bool Provider availability
     */
    public function isEnabled(): bool;

    /**
     * Get the priority order for this provider
     * Lower numbers = higher priority
     *
     * @return int Priority order
     */
    public function getPriority(): int;
}
