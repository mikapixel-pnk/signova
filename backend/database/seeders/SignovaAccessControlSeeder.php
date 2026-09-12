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
            ['invoice', 'invoice.issue', 'Menerbitkan Tagihan', false],
            ['invoice', 'invoice.void', 'Membatalkan Tagihan', true],

            ['payment', 'payment.view', 'Melihat Pembayaran', true],
            ['payment', 'payment.record', 'Mencatat Pembayaran', true],
            ['payment', 'payment.verify', 'Memverifikasi Pembayaran', true],
            ['payment', 'payment.reverse', 'Membalik Pembayaran', true],

            ['finance', 'finance.receivable.view', 'Melihat Piutang', true],
            ['finance', 'finance.cash_bank.view', 'Melihat Kas & Bank', true],
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
}
