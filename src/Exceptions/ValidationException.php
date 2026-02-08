<?php

declare(strict_types=1);

namespace BacklinkChecker\Exceptions;

final class ValidationException extends AppException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(string $message, array $details = [])
    {
        parent::__construct($message, 'validation_error', 422, $details);
    }
}
