<?php

namespace App\Http\Middleware;

use App\Authorization\Platform\PlatformCapabilityResolver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlatformCapability
{
    public function __construct(
        private readonly PlatformCapabilityResolver $resolver,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $capabilityCode
    ): Response {
        $user = $request->user();

        if (! $user) {
            return $this->error(
                401,
                'AUTH_REQUIRED',
                'Autentikasi diperlukan.'
            );
        }

        if (
            ! $this->resolver->allows(
                $user,
                $capabilityCode
            )
        ) {
            return $this->error(
                403,
                'FORBIDDEN_PLATFORM_CAPABILITY',
                'Anda tidak memiliki akses platform untuk tindakan ini.',
                [
                    'capability' =>
                        $capabilityCode,
                ]
            );
        }

        return $next($request);
    }

    private function error(
        int $status,
        string $code,
        string $message,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'meta' => $meta,
            ],
        ], $status);
    }
}
