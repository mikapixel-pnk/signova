<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    public static function success(
        Request $request,
        mixed $data = null,
        int $status = 200,
        ?string $message = null,
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'data' => $data,
            'meta' => [
                'request_id' =>
                    $request->attributes->get(
                        'request_id'
                    ),
                ...$meta,
            ],
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json(
            $payload,
            $status
        );
    }

    public static function error(
        Request $request,
        string $code,
        string $message,
        int $status,
        array $details = []
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => [
                'request_id' =>
                    $request->attributes->get(
                        'request_id'
                    ),
            ],
        ], $status);
    }
}
