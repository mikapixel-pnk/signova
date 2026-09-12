<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
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

    public function login(LoginRequest $request): JsonResponse
    {
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

        $memberships = DB::table('tenant_users as tu')
            ->join(
                'tenants as t',
                't.id',
                '=',
                'tu.tenant_id'
            )
            ->where('tu.user_id', $user->id)
            ->where('tu.status', 'ACTIVE')
            ->where('t.lifecycle_status', 'ACTIVE')
            ->orderBy('tu.joined_at')
            ->select([
                't.id',
                't.name',
                't.slug',
                't.lifecycle_status',
                't.timezone',
                't.locale',
            ])
            ->get();

        if ($memberships->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => [
                    'Akun tidak memiliki workspace aktif.',
                ],
            ]);
        }

        $token = $user->createToken(
            $credentials['device_name'] ?? 'signova-api'
        )->plainTextToken;

        $tenant = null;

        if ($memberships->count() === 1) {
            $tenant = $this->tenantPayload(
                $memberships->first()->id
            );
        }

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'user' => $this->userPayload($user),
                'tenant' => $tenant,
                'tenants' => $memberships
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'slug' => $item->slug,
                        'lifecycle_status' =>
                            $item->lifecycle_status,
                        'timezone' => $item->timezone,
                        'locale' => $item->locale,
                    ])
                    ->values()
                    ->all(),
                'requires_tenant_selection' =>
                    $memberships->count() > 1,
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
