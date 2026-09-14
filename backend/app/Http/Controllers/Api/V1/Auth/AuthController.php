<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\AuthAccessContextService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request,
        CreateTenantWorkspaceAction $action
    ): JsonResponse {
        $result = $action->execute(
            $request->validated()
        );

        $user = User::query()
            ->findOrFail($result['user_id']);

        $token = $user->createToken(
            'signova-api'
        )->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil.',
            'data' => [
                'user' => $this->userPayload($user),
                'tenant' => $this->tenantPayload(
                    $result['tenant_id']
                ),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function login(
        LoginRequest $request,
        AuthAccessContextService $accessContext
    ): JsonResponse {
        $credentials = $request->validated();

        $user = User::query()
            ->where('email', strtolower($credentials['email']))
            ->first();

        if (
            ! $user ||
            ! Hash::check($credentials['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'Email atau kata sandi tidak valid.',
                ],
            ]);
        }

        if ($user->auth_status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'email' => [
                    'Akun tidak aktif.',
                ],
            ]);
        }

        $context = $accessContext->build($user);

        if (! $context['has_access']) {
            throw ValidationException::withMessages([
                'email' => [
                    'Akun tidak memiliki akses aktif.',
                ],
            ]);
        }

        $token = $user->createToken(
            $credentials['device_name'] ?? 'signova-api'
        )->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'user' => $this->userPayload($user),

                /*
                 * Legacy tenant fields are retained temporarily
                 * for backward compatibility with existing clients.
                 */
                'tenant' =>
                    $context['default_context']
                    && $context['default_context']['type']
                        === 'TENANT'
                        ? $this->tenantPayload(
                            $context['default_context']['tenant_id']
                        )
                        : null,

                'tenants' =>
                    $context['access']['tenants'],

                'requires_tenant_selection' =>
                    count(
                        $context['access']['tenants']
                    ) > 1,

                'access' =>
                    $context['access'],

                'default_context' =>
                    $context['default_context'],

                'requires_context_selection' =>
                    $context[
                        'requires_context_selection'
                    ],

                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function me(
        Request $request,
        TenantContext $tenantContext
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => $this->userPayload($user),
                'tenant' => $this->tenantPayload(
                    $tenantContext->tenantId()
                ),
            ],
        ]);
    }

    public function context(
        Request $request,
        AuthAccessContextService $accessContext
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => array_merge(
                [
                    'user' =>
                        $this->userPayload($user),
                ],
                $accessContext->build($user)
            ),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()
            ?->currentAccessToken()
            ?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'auth_status' => $user->auth_status,
        ];
    }

    private function tenantPayload(string $tenantId): ?array
    {
        $tenant = DB::table('tenants')
            ->where('id', $tenantId)
            ->first();

        if (! $tenant) {
            return null;
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'lifecycle_status' => $tenant->lifecycle_status,
            'timezone' => $tenant->timezone,
            'locale' => $tenant->locale,
        ];
    }
}
