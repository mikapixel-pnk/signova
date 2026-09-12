<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $requestId = $request->header(
            'X-Request-ID'
        );

        if (
            ! is_string($requestId) ||
            trim($requestId) === ''
        ) {
            $requestId = 'req_' . Str::lower(
                (string) Str::ulid()
            );
        }

        $request->attributes->set(
            'request_id',
            $requestId
        );

        $response = $next($request);

        $response->headers->set(
            'X-Request-ID',
            $requestId
        );

        return $response;
    }
}
