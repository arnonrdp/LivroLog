<?php

namespace App\Exceptions;

use Exception;

class FirebaseImportException extends Exception
{
    public static function fileNotFound(string $filename): self
    {
        return new self("Firebase data file not found: {$filename}");
    }

    public static function invalidDataFormat(string $message = null): self
    {
        $defaultMessage = "Invalid Firebase data format";
        return new self($message ? "{$defaultMessage}: {$message}" : $defaultMessage);
    }

    public static function processingError(string $message): self
    {
        return new self("Firebase data processing error: {$message}");
    }
}
