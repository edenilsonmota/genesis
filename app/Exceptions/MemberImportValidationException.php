<?php

namespace App\Exceptions;

use RuntimeException;

class MemberImportValidationException extends RuntimeException
{
    /** @param list<array{field: string, message: string, value?: string|null}> $errors */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
