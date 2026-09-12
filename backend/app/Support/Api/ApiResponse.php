<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiResponse
{
    public static function success(
        Request $request,
        mixed $data = null,
        int $status = 200,
        ?string $message = null,
        array $meta = []
    ): JsonResponse {
        $requestId = self::requestId($request);

        $payload = [
            'success' => true,
            'data' => $data,
            'meta' => [
                'request_id' => $requestId,
                ...$meta,
            ],
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()
            ->json($payload, $status)
            ->header(
                'X-Request-ID',
                $requestId
            );
    }

    public static function error(
        Request $request,
        string $code,
        string $message,
        int $status,
        array $details = []
    ): JsonResponse {
        $requestId = self::requestId($request);

        return response()
            ->json([
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                    'details' => $details,
                ],
                'meta' => [
                    'request_id' => $requestId,
                ],
            ], $status)
            ->header(
                'X-Request-ID',
                $requestId
            );
    }

    private static function requestId(
        Request $request
    ): string {
        $requestId = $request->attributes->get(
            'request_id'
        );

        if (
            ! is_string($requestId) ||
            trim($requestId) === ''
        ) {
            $requestId = 'req_' . Str::lower(
                (string) Str::ulid()
            );

            $request->attributes->set(
                'request_id',
                $requestId
            );
        }

        return $requestId;
    }
}
