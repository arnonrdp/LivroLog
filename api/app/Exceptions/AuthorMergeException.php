<?php

namespace App\Exceptions;

class AuthorMergeException extends \Exception
{
    public static function targetAuthorNotFound(int $targetId): self
    {
        return new self("Target author with ID {$targetId} not found");
    }

    public static function sourceAuthorNotFound(int $sourceId): self
    {
        return new self("Source author with ID {$sourceId} not found");
    }

    public static function cannotMergeWithSelf(): self
    {
        return new self('Cannot merge author with itself');
    }
}
