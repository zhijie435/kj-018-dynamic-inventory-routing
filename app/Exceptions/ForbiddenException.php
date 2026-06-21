<?php

namespace App\Exceptions;

class ForbiddenException extends BaseException
{
    public function __construct(string $message = 'You do not have permission to perform this action.', array $context = [])
    {
        parent::__construct(
            $message,
            'FORBIDDEN',
            403,
            $context
        );
    }
}
