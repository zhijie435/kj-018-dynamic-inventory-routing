<?php

namespace App\Exceptions;

class NotFoundException extends BaseException
{
    public function __construct(string $entity = 'Resource', array $context = [])
    {
        parent::__construct(
            "{$entity} not found.",
            'NOT_FOUND',
            404,
            $context
        );
    }
}
