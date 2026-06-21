<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait ApiResponse
{
    protected function successResponse(
        mixed $data = null,
        string $message = '',
        int $statusCode = 200
    ): JsonResponse {
        $payload = [];

        if ($data !== null) {
            $payload['data'] = $this->transformData($data);
        }

        if ($message !== '') {
            $payload['message'] = $message;
        }

        return response()->json($payload, $statusCode);
    }

    protected function createdResponse(
        mixed $data = null,
        string $message = 'Resource created successfully.'
    ): JsonResponse {
        return $this->successResponse($data, $message, 201);
    }

    protected function deletedResponse(string $message = 'Resource deleted successfully.'): JsonResponse
    {
        return response()->json(['message' => $message], 200);
    }

    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    private function transformData(mixed $data): mixed
    {
        if ($data instanceof LengthAwarePaginator) {
            return [
                'items' => $data->items(),
                'pagination' => [
                    'total' => $data->total(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ];
        }

        if ($data instanceof Model && method_exists($data, 'toApiArray')) {
            return $data->toApiArray();
        }

        return $data;
    }
}
