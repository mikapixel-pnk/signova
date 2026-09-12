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

        $tenantId = $this->resolver->resolve(
            $user,
            $requestedTenantId
        );

        if (! $tenantId) {
            return $this->tenantAccessDeniedResponse(
                $requestedTenantId
            );
        }

        $this->context->set(
            $tenantId,
            $user->id
        );

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }

    private function tenantAccessDeniedResponse(
        ?string $requestedTenantId
    ): JsonResponse {
        if ($requestedTenantId !== null) {
            return response()->json([
                'message' => 'Workspace tidak tersedia atau tidak dapat diakses.',
                'error' => [
                    'code' => 'TENANT_ACCESS_DENIED',
                ],
            ], 403);
        }

        return response()->json([
            'message' => 'Akun tidak memiliki workspace aktif.',
            'error' => [
                'code' => 'ACTIVE_TENANT_REQUIRED',
            ],
        ], 403);
    }
}
