<?php

declare(strict_types=1);

namespace BacklinkChecker\Exceptions;

final class AuthenticationException extends AppException
{
    public function __construct(string $message = 'Authentication required')
    {
        parent::__construct($message, 'auth_required', 401);
    }
}
