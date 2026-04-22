<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(
        mixed $data = null,
        string $message = 'OK',
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json(
            [
                'success' => true,
                'data' => $data,
                'meta' => $meta,
                'message' => $message,
            ],
            $status,
        );
    }

    protected function failure(
        string $message,
        mixed $data = null,
        array $meta = [],
        int $status = 400,
        array $errors = [],
    ): JsonResponse {
        return response()->json(
            [
                'success' => false,
                'data' => $data,
                'meta' => $meta,
                'message' => $message,
                'errors' => $errors,
            ],
            $status,
        );
    }
}
