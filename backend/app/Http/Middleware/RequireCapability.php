<?php

namespace App\Http\Middleware;

use App\Authorization\EffectiveCapabilityResolver;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCapability
{
    public function __construct(
        private readonly EffectiveCapabilityResolver $resolver,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $capabilityCode
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

        if (
            ! $this->resolver->allows(
                $user,
                $capabilityCode
            )
        ) {
            return ApiResponse::error(
                $request,
                'FORBIDDEN_CAPABILITY',
                'Anda tidak memiliki hak akses untuk tindakan ini.',
                403,
                [
                    'capability' =>
                        $capabilityCode,
                ]
            );
        }

        return $next($request);
    }
}
