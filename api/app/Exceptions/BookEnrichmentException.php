<?php

namespace App\Exceptions;

use Exception;

class BookEnrichmentException extends Exception
{
    public static function apiRequestFailed(string $message): self
    {
        return new self("Book enrichment API request failed: {$message}");
    }

    public static function invalidBookData(string $message): self
    {
        return new self("Invalid book data: {$message}");
    }

    public static function enrichmentServiceUnavailable(): self
    {
        return new self('Book enrichment service is currently unavailable');
    }
}
