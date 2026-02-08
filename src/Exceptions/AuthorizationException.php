<?php

declare(strict_types=1);

namespace BacklinkChecker\Exceptions;

final class AuthorizationException extends AppException
{
    public function __construct(string $message = 'Insufficient permissions')
    {
        parent::__construct($message, 'permission_denied', 403);
    }
}
