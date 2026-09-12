<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantContextResolver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContextResolver $resolver,
        private readonly TenantContext $context,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $requestedTenantId = $request->header(
            'X-Signova-Tenant'
        );

        if (is_string($requestedTenantId)) {
            $requestedTenantId = trim(
                $requestedTenantId
            );

            if ($requestedTenantId === '') {
                $requestedTenantId = null;
            }
        }

        $resolution = $this->resolver->resolve(
            $user,
            $requestedTenantId
        );

        if (
            $resolution['status']
            !== TenantContextResolver::RESOLVED
        ) {
            return $this->resolutionError(
                $resolution['status']
            );
        }

        $this->context->set(
            $resolution['tenant_id'],
            $user->id
        );

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }

    private function resolutionError(
        string $status
    ): JsonResponse {
        return match ($status) {
            TenantContextResolver::TENANT_SELECTION_REQUIRED =>
                response()->json([
                    'message' =>
                        'Pilih workspace yang akan digunakan.',
                    'error' => [
                        'code' =>
                            'TENANT_SELECTION_REQUIRED',
                    ],
                ], 409),

            TenantContextResolver::TENANT_ACCESS_DENIED =>
                response()->json([
                    'message' =>
                        'Workspace tidak tersedia atau tidak dapat diakses.',
                    'error' => [
                        'code' =>
                            'TENANT_ACCESS_DENIED',
                    ],
                ], 403),

            default =>
                response()->json([
                    'message' =>
                        'Akun tidak memiliki workspace aktif.',
                    'error' => [
                        'code' =>
                            'ACTIVE_TENANT_REQUIRED',
                    ],
                ], 403),
        };
    }
}
