<?php

namespace App\Exceptions;

class ImportException extends \Exception
{
    public static function fileNotFound(string $filePath): self
    {
        return new self("File not found: {$filePath}");
    }

    public static function invalidJson(string $error): self
    {
        return new self("Invalid JSON: {$error}");
    }

    public static function invalidUrl(string $url): self
    {
        return new self("Invalid URL format: {$url}");
    }

    public static function unsupportedScheme(string $url): self
    {
        return new self("Only HTTP and HTTPS URLs are allowed: {$url}");
    }

    public static function fetchFailed(string $url): self
    {
        return new self("Failed to fetch data from URL: {$url}");
    }
}
