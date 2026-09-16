<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Tenancy\BusinessContext;
use App\Tenancy\BusinessContextResolver;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBusinessContext
{
    public function __construct(
        private readonly BusinessContextResolver $resolver,
        private readonly BusinessContext $businessContext,
        private readonly TenantContext $tenantContext,
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

        $requestedBusinessId = $request->header(
            'X-Signova-Business'
        );

        if (is_string($requestedBusinessId)) {
            $requestedBusinessId = trim(
                $requestedBusinessId
            );

            if ($requestedBusinessId === '') {
                $requestedBusinessId = null;
            }
        }

        $tenantId =
            $this->tenantContext->tenantId();

        $resolution = $this->resolver->resolve(
            $tenantId,
            $requestedBusinessId
        );

        if (
            $resolution['status']
            !== BusinessContextResolver::RESOLVED
        ) {
            return $this->resolutionError(
                $request,
                $resolution['status']
            );
        }

        $this->businessContext->set(
            $tenantId,
            $resolution['business_id'],
            $user->id
        );

        try {
            return $next($request);
        } finally {
            $this->businessContext->clear();
        }
    }

    private function resolutionError(
        Request $request,
        string $status
    ): JsonResponse {
        return match ($status) {
            BusinessContextResolver::BUSINESS_SELECTION_REQUIRED =>
                ApiResponse::error(
                    $request,
                    'BUSINESS_SELECTION_REQUIRED',
                    'Pilih usaha yang akan digunakan.',
                    409
                ),

            BusinessContextResolver::BUSINESS_ACCESS_DENIED =>
                ApiResponse::error(
                    $request,
                    'BUSINESS_ACCESS_DENIED',
                    'Usaha tidak tersedia atau tidak dapat diakses.',
                    403
                ),

            default =>
                ApiResponse::error(
                    $request,
                    'ACTIVE_BUSINESS_REQUIRED',
                    'Workspace tidak memiliki usaha aktif.',
                    403
                ),
        };
    }
}
