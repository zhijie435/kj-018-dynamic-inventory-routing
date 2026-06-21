<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

abstract class BaseException extends Exception
{
    protected int $httpStatusCode;
    protected string $errorCode;
    protected array $context;

    public function __construct(
        string $message = '',
        string $errorCode = 'INTERNAL_ERROR',
        int $httpStatusCode = 500,
        array $context = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->httpStatusCode = $httpStatusCode;
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function render(): JsonResponse
    {
        $payload = [
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage() ?: 'An error occurred.',
            ],
        ];

        if (!empty($this->context)) {
            $payload['error']['context'] = $this->context;
        }

        return response()->json($payload, $this->httpStatusCode);
    }
}
