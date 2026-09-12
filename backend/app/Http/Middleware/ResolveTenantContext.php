<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Api\ApiResponse;
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
            return ApiResponse::error(
                $request,
                'AUTH_REQUIRED',
                'Autentikasi diperlukan.',
                401
            );
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
                $request,
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
        Request $request,
        string $status
    ): JsonResponse {
        return match ($status) {
            TenantContextResolver::TENANT_SELECTION_REQUIRED =>
                ApiResponse::error(
                    $request,
                    'TENANT_SELECTION_REQUIRED',
                    'Pilih workspace yang akan digunakan.',
                    409
                ),

            TenantContextResolver::TENANT_ACCESS_DENIED =>
                ApiResponse::error(
                    $request,
                    'TENANT_ACCESS_DENIED',
                    'Workspace tidak tersedia atau tidak dapat diakses.',
                    403
                ),

            default =>
                ApiResponse::error(
                    $request,
                    'ACTIVE_TENANT_REQUIRED',
                    'Akun tidak memiliki workspace aktif.',
                    403
                ),
        };
    }
}
