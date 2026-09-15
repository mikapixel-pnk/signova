<?php

namespace App\Actions\Tenancy;

use App\Actions\MasterData\SeedTenantMasterDataAction;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class CreateTenantWorkspaceAction
{
    public function __construct(
        private readonly SeedTenantMasterDataAction $seedTenantMasterData,
    ) {
    }

    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $now = now();

            $userId = (string) Str::ulid();
            $tenantId = (string) Str::ulid();
            $businessProfileId = (string) Str::ulid();
            $roleId = (string) Str::ulid();

            $email = Str::lower(trim($data['email']));
            $userName = trim($data['name']);
            $tenantName = trim($data['tenant_name']);

            $timezone = $data['timezone'] ?? 'Asia/Jakarta';
            $locale = $data['locale'] ?? 'id';

            $tenantSlug = $this->generateUniqueTenantSlug($tenantName);

            DB::table('users')->insert([
                'id' => $userId,
                'name' => $userName,
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'auth_status' => 'ACTIVE',
                'password' => Hash::make($data['password']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('tenants')->insert([
                'id' => $tenantId,
                'name' => $tenantName,
                'code' => null,
                'slug' => $tenantSlug,
                'lifecycle_status' => 'ACTIVE',
                'timezone' => $timezone,
                'locale' => $locale,
                'primary_owner_user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('business_profiles')->insert([
                'id' => $businessProfileId,
                'tenant_id' => $tenantId,
                'name' => $tenantName,
                'legal_name' => null,
                'address' => null,
                'city' => null,
                'province' => null,
                'postal_code' => null,
                'phone' => null,
                'whatsapp' => null,
                'email' => null,
                'website' => null,
                'tax_id' => null,
                'is_default' => true,
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('tenant_users')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'status' => 'ACTIVE',
                'joined_at' => $now,
                'context' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $masterRole = DB::table('master_roles')
                ->where('code', 'OWNER')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $masterRole) {
                throw new RuntimeException(
                    'Master role OWNER is not available.'
                );
            }

            DB::table('roles')->insert([
                'id' => $roleId,
                'tenant_id' => $tenantId,
                'master_role_id' => $masterRole->id,
                'name' => $masterRole->name,
                'code' => 'OWNER',
                'status' => 'ACTIVE',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $masterCapabilities = DB::table('master_role_capabilities')
                ->where('master_role_id', $masterRole->id)
                ->get();

            if ($masterCapabilities->isEmpty()) {
                throw new RuntimeException(
                    'Master role OWNER has no baseline capabilities.'
                );
            }

            $roleCapabilities = $masterCapabilities
                ->map(fn ($item) => [
                    'role_id' => $roleId,
                    'capability_id' => $item->capability_id,
                    'effect' => $item->effect,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            DB::table('role_capabilities')->insert(
                $roleCapabilities
            );

            DB::table('tenant_user_roles')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'role_id' => $roleId,
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->seedTenantMasterData->execute(
                $tenantId
            );

            return [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'owner_role_id' => $roleId,
                'tenant_slug' => $tenantSlug,
            ];
        });
    }

    private function generateUniqueTenantSlug(string $tenantName): string
    {
        $base = Str::slug($tenantName);

        if ($base === '') {
            $base = 'usaha';
        }

        if (! DB::table('tenants')->where('slug', $base)->exists()) {
            return $base;
        }

        do {
            $slug = $base . '-' . Str::lower(
                Str::random(6)
            );
        } while (
            DB::table('tenants')
                ->where('slug', $slug)
                ->exists()
        );

        return $slug;
    }
}
