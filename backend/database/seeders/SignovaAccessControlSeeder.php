<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SignovaAccessControlSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $featureIds = $this->seedFeatures();
            $this->seedCapabilities($featureIds);
            $this->seedMasterRoles();
            $this->seedMasterRoleCapabilities();
            $this->syncExistingSystemRoleCapabilities();
        });
    }

    private function seedFeatures(): array
    {
        $features = [
            'dashboard' => 'Beranda',
            'customer' => 'Pelanggan',
            'catalog' => 'Produk & Jasa',
            'quotation' => 'Penawaran',
            'invoice' => 'Tagihan',
            'payment' => 'Pembayaran',
            'finance' => 'Keuangan',
            'project' => 'Proyek',
            'team' => 'Tim & Hak Akses',
            'settings' => 'Pengaturan',
        ];

        $ids = [];

        foreach ($features as $code => $name) {
            $existing = DB::table('features')
                ->where('code', $code)
                ->first();

            if ($existing) {
                DB::table('features')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $name,
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);

                $ids[$code] = $existing->id;
                continue;
            }

            $id = (string) Str::ulid();

            DB::table('features')->insert([
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ids[$code] = $id;
        }

        return $ids;
    }

    private function seedCapabilities(array $featureIds): void
    {
        $capabilities = [
            ['dashboard', 'dashboard.view', 'Melihat Beranda', false],

            ['customer', 'customer.view', 'Melihat Pelanggan', false],
            ['customer', 'customer.create', 'Membuat Pelanggan', false],
            ['customer', 'customer.update', 'Mengubah Pelanggan', false],

            ['catalog', 'catalog.view', 'Melihat Produk & Jasa', false],
            ['catalog', 'catalog.manage', 'Mengelola Produk & Jasa', false],

            ['quotation', 'quotation.view', 'Melihat Penawaran', false],
            ['quotation', 'quotation.create', 'Membuat Penawaran', false],
            ['quotation', 'quotation.update', 'Mengubah Penawaran', false],
            ['quotation', 'quotation.issue', 'Mengirim Penawaran', false],

            ['invoice', 'invoice.view', 'Melihat Tagihan', false],
            ['invoice', 'invoice.create', 'Membuat Tagihan', false],
            ['invoice', 'invoice.update', 'Mengubah Tagihan', false],
            ['invoice', 'invoice.issue', 'Menerbitkan Tagihan', false],
            ['invoice', 'invoice.void', 'Membatalkan Tagihan', true],

            ['payment', 'payment.view', 'Melihat Pembayaran', true],
            ['payment', 'payment.record', 'Mencatat Pembayaran', true],
            ['payment', 'payment.verify', 'Memverifikasi Pembayaran', true],
            ['payment', 'payment.reverse', 'Membalik Pembayaran', true],

            ['finance', 'finance.receivable.view', 'Melihat Piutang', true],
            ['finance', 'finance.summary.view', 'Melihat Ringkasan Keuangan', true],
            ['finance', 'finance.cash_bank.view', 'Melihat Kas & Bank', true],
            ['finance', 'finance.cash_bank.manage', 'Mengelola Kas & Bank', true],
            ['finance', 'finance.income.view', 'Melihat Pemasukan', true],
            ['finance', 'finance.income.manage', 'Mengelola Pemasukan', true],
            ['finance', 'finance.expense.view', 'Melihat Pengeluaran', true],
            ['finance', 'finance.expense.manage', 'Mengelola Pengeluaran', true],
            ['finance', 'finance.margin.view', 'Melihat Margin', true],

            ['project', 'project.view', 'Melihat Proyek', false],
            ['project', 'project.create', 'Membuat Proyek', false],
            ['project', 'project.update', 'Mengubah Proyek', false],

            ['team', 'team.user.view', 'Melihat Pengguna', false],
            ['team', 'team.user.manage', 'Mengelola Pengguna', true],
            ['team', 'team.role.view', 'Melihat Peran & Hak Akses', false],
            ['team', 'team.role.manage', 'Mengelola Peran & Hak Akses', true],

            ['settings', 'settings.view', 'Melihat Pengaturan', false],
            ['settings', 'settings.manage', 'Mengelola Pengaturan', true],
        ];

        foreach ($capabilities as [$featureCode, $code, $name, $sensitive]) {
            $existing = DB::table('capabilities')
                ->where('code', $code)
                ->first();

            $data = [
                'feature_id' => $featureIds[$featureCode],
                'name' => $name,
                'is_sensitive' => $sensitive,
                'is_active' => true,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('capabilities')
                    ->where('id', $existing->id)
                    ->update($data);

                continue;
            }

            DB::table('capabilities')->insert(array_merge($data, [
                'id' => (string) Str::ulid(),
                'code' => $code,
                'created_at' => now(),
            ]));
        }
    }

    private function seedMasterRoles(): void
    {
        $masterRoles = [
            'OWNER' => 'Pemilik / Owner',
            'ADMIN' => 'Administrator',
            'SALES' => 'Penjualan / Sales',
            'FINANCE' => 'Keuangan',
        ];

        foreach ($masterRoles as $code => $name) {
            $existing = DB::table('master_roles')
                ->where('code', $code)
                ->first();

            if ($existing) {
                DB::table('master_roles')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $name,
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('master_roles')->insert([
                'id' => (string) Str::ulid(),
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedMasterRoleCapabilities(): void
    {
        $matrix = [
            'OWNER' => ['*'],

            'ADMIN' => [
                'dashboard.view',
                'customer.view',
                'customer.create',
                'customer.update',
                'catalog.view',
                'catalog.manage',
                'quotation.view',
                'project.view',
                'project.create',
                'project.update',
                'team.user.view',
                'team.user.manage',
                'team.role.view',
                'team.role.manage',
                'settings.view',
                'settings.manage',
            ],

            'SALES' => [
                'dashboard.view',
                'customer.view',
                'customer.create',
                'customer.update',
                'catalog.view',
                'quotation.view',
                'quotation.create',
                'quotation.update',
                'quotation.issue',
                'invoice.view',
                'project.view',
                'project.create',
                'project.update',
            ],

            'FINANCE' => [
                'dashboard.view',
                'customer.view',
                'invoice.view',
                'invoice.create',
                'invoice.update',
                'invoice.issue',
                'invoice.void',
                'payment.view',
                'payment.record',
                'payment.verify',
                'payment.reverse',
                'finance.receivable.view',
                'finance.summary.view',
                'finance.cash_bank.view',
                'finance.cash_bank.manage',
                'finance.income.view',
                'finance.income.manage',
                'finance.expense.view',
                'finance.expense.manage',
                'finance.margin.view',
                'project.view',
            ],
        ];

        foreach ($matrix as $roleCode => $capabilityCodes) {
            $role = DB::table('master_roles')
                ->where('code', $roleCode)
                ->first();

            if (! $role) {
                continue;
            }

            $query = DB::table('capabilities')
                ->where('is_active', true);

            if ($capabilityCodes !== ['*']) {
                $query->whereIn('code', $capabilityCodes);
            }

            $capabilities = $query->get();

            foreach ($capabilities as $capability) {
                $exists = DB::table('master_role_capabilities')
                    ->where('master_role_id', $role->id)
                    ->where('capability_id', $capability->id)
                    ->exists();

                if ($exists) {
                    DB::table('master_role_capabilities')
                        ->where('master_role_id', $role->id)
                        ->where('capability_id', $capability->id)
                        ->update([
                            'effect' => 'ALLOW',
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                DB::table('master_role_capabilities')->insert([
                    'master_role_id' => $role->id,
                    'capability_id' => $capability->id,
                    'effect' => 'ALLOW',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }


    private function syncExistingSystemRoleCapabilities(): void
    {
        $roles = DB::table('roles')
            ->where('is_system', true)
            ->whereNotNull('master_role_id')
            ->get([
                'id',
                'master_role_id',
            ]);

        foreach ($roles as $role) {
            $masterCapabilities =
                DB::table('master_role_capabilities')
                    ->where(
                        'master_role_id',
                        $role->master_role_id
                    )
                    ->get([
                        'capability_id',
                        'effect',
                    ]);

            foreach ($masterCapabilities as $capability) {
                $exists =
                    DB::table('role_capabilities')
                        ->where(
                            'role_id',
                            $role->id
                        )
                        ->where(
                            'capability_id',
                            $capability->capability_id
                        )
                        ->exists();

                if ($exists) {
                    /*
                     * Jangan overwrite ALLOW / DENY existing.
                     * Tenant-level explicit override harus tetap dihormati.
                     */
                    continue;
                }

                DB::table('role_capabilities')->insert([
                    'role_id' =>
                        $role->id,

                    'capability_id' =>
                        $capability->capability_id,

                    'effect' =>
                        $capability->effect,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
            }
        }
    }

}
