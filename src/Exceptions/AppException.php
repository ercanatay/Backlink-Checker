<?php

declare(strict_types=1);

namespace BacklinkChecker\Exceptions;

use Exception;

class AppException extends Exception
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatus = 400,
        private readonly array $details = []
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
