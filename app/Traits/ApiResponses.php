<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;

trait ApiResponses
{
    public function ok(string $message = 'success', array|JsonResource $data = [], array $meta = []): JsonResponse
    {
        return $this->response($message, $data, 200, $meta);
    }

    public function created(string $message = 'success', array|JsonResource $data = [], array $meta = []): JsonResponse
    {
        return $this->response($message, $data, 201, $meta);
    }

    public function error(string $message, array|JsonResource $data = [], int $statusCode = 400, array $meta = []): JsonResponse
    {
        return $this->response($message, $data, $statusCode, $meta);
    }

    public function response(string $message, array|JsonResource $data = [], int $statusCode = 200, array $meta = []): JsonResponse
    {
        $isPaginated = $data instanceof ResourceCollection
            && ($data->resource instanceof AbstractPaginator || $data->resource instanceof AbstractCursorPaginator);

        if ($isPaginated) {
            $resolved = $data->response(request())->getData(true);

            return response()->json([
                'message' => $message,
                'data' => $resolved['data'] ?? [],
                'meta' => array_merge($resolved['meta'] ?? [], $meta),
                'links' => $resolved['links'] ?? [],
                'status' => $statusCode,
            ], $statusCode);
        }

        $payload = [
            'message' => $message,
            'data' => $data,
            'status' => $statusCode,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $statusCode);
    }
}
