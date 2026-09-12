<?php

namespace App\Http\Middleware;

use App\Authorization\EffectiveCapabilityResolver;
use App\Models\User;
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
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (
            ! $this->resolver->allows(
                $user,
                $capabilityCode
            )
        ) {
            return response()->json([
                'message' =>
                    'Anda tidak memiliki hak akses untuk tindakan ini.',
                'error' => [
                    'code' => 'FORBIDDEN_CAPABILITY',
                    'capability' => $capabilityCode,
                ],
            ], 403);
        }

        return $next($request);
    }
}
