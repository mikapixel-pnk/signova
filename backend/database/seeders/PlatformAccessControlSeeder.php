<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformAccessControlSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $capabilityIds =
                $this->seedCapabilities();

            $superAdminRoleId =
                $this->seedSuperAdminRole();

            $this->syncSuperAdminCapabilities(
                $superAdminRoleId,
                $capabilityIds
            );
        });
    }

    private function seedCapabilities(): array
    {
        $capabilities = [
            [
                'platform.dashboard.view',
                'Melihat Beranda SaaS',
                false,
            ],

            [
                'platform.tenant.view',
                'Melihat Tenant',
                true,
            ],
            [
                'platform.tenant.manage',
                'Mengelola Tenant',
                true,
            ],

            [
                'platform.subscription.view',
                'Melihat Langganan',
                true,
            ],
            [
                'platform.subscription.manage',
                'Mengelola Langganan',
                true,
            ],

            [
                'platform.plan.view',
                'Melihat Paket & Harga',
                false,
            ],
            [
                'platform.plan.manage',
                'Mengelola Paket & Harga',
                true,
            ],

            [
                'platform.order.view',
                'Melihat Pesanan Paket',
                true,
            ],
            [
                'platform.order.manage',
                'Mengelola Pesanan Paket',
                true,
            ],

            [
                'platform.usage.view',
                'Melihat Kuota & Pemakaian',
                true,
            ],

            [
                'platform.integration.view',
                'Melihat Status Integrasi',
                true,
            ],

            [
                'platform.notification.view',
                'Melihat Notifikasi Platform',
                true,
            ],

            [
                'platform.audit.view',
                'Melihat Audit Platform',
                true,
            ],

            [
                'platform.settings.view',
                'Melihat Pengaturan SaaS',
                true,
            ],
            [
                'platform.settings.manage',
                'Mengelola Pengaturan SaaS',
                true,
            ],
        ];

        $ids = [];

        foreach (
            $capabilities
            as [$code, $name, $sensitive]
        ) {
            $existing = DB::table(
                'platform_capabilities'
            )
                ->where('code', $code)
                ->first();

            $data = [
                'name' => $name,
                'is_sensitive' => $sensitive,
                'is_active' => true,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table(
                    'platform_capabilities'
                )
                    ->where('id', $existing->id)
                    ->update($data);

                $ids[$code] =
                    (string) $existing->id;

                continue;
            }

            $id = (string) Str::ulid();

            DB::table(
                'platform_capabilities'
            )->insert(array_merge(
                $data,
                [
                    'id' => $id,
                    'code' => $code,
                    'created_at' => now(),
                ]
            ));

            $ids[$code] = $id;
        }

        return $ids;
    }

    private function seedSuperAdminRole(): string
    {
        $existing = DB::table(
            'platform_roles'
        )
            ->where(
                'code',
                'SUPER_ADMIN'
            )
            ->first();

        if ($existing) {
            DB::table(
                'platform_roles'
            )
                ->where('id', $existing->id)
                ->update([
                    'name' =>
                        'Super Admin SIGNOVA',
                    'description' =>
                        'Pengelola platform SaaS SIGNOVA.',
                    'status' =>
                        'ACTIVE',
                    'is_system' =>
                        true,
                    'updated_at' =>
                        now(),
                ]);

            return (string) $existing->id;
        }

        $id = (string) Str::ulid();

        DB::table(
            'platform_roles'
        )->insert([
            'id' => $id,
            'code' => 'SUPER_ADMIN',
            'name' => 'Super Admin SIGNOVA',
            'description' =>
                'Pengelola platform SaaS SIGNOVA.',
            'status' => 'ACTIVE',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function syncSuperAdminCapabilities(
        string $roleId,
        array $capabilityIds
    ): void {
        foreach ($capabilityIds as $capabilityId) {
            $exists = DB::table(
                'platform_role_capabilities'
            )
                ->where(
                    'platform_role_id',
                    $roleId
                )
                ->where(
                    'platform_capability_id',
                    $capabilityId
                )
                ->exists();

            /*
             * Jangan overwrite ALLOW / DENY existing.
             * Explicit platform override harus tetap dihormati.
             */
            if ($exists) {
                continue;
            }

            DB::table(
                'platform_role_capabilities'
            )->insert([
                'platform_role_id' =>
                    $roleId,

                'platform_capability_id' =>
                    $capabilityId,

                'effect' =>
                    'ALLOW',

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
        }
    }
}
