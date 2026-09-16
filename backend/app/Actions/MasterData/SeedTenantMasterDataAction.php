<?php

namespace App\Actions\MasterData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedTenantMasterDataAction
{
    public function execute(
        string $tenantId,
        string $businessId
    ): void {
        $this->seedUnits(
            $tenantId,
            $businessId
        );
    }

    private function seedUnits(
        string $tenantId,
        string $businessId
    ): void
    {
        $units = [
            [
                'code' => 'PCS',
                'name' => 'Pieces',
                'symbol' => 'pcs',
                'unit_type' => 'COUNT',
                'decimal_precision' => 0,
            ],
            [
                'code' => 'UNIT',
                'name' => 'Unit',
                'symbol' => 'unit',
                'unit_type' => 'COUNT',
                'decimal_precision' => 0,
            ],
            [
                'code' => 'CM',
                'name' => 'Centimeter',
                'symbol' => 'cm',
                'unit_type' => 'LENGTH',
                'decimal_precision' => 2,
            ],
            [
                'code' => 'M',
                'name' => 'Meter',
                'symbol' => 'm',
                'unit_type' => 'LENGTH',
                'decimal_precision' => 3,
            ],
            [
                'code' => 'CM2',
                'name' => 'Centimeter Persegi',
                'symbol' => 'cm²',
                'unit_type' => 'AREA',
                'decimal_precision' => 2,
            ],
            [
                'code' => 'M2',
                'name' => 'Meter Persegi',
                'symbol' => 'm²',
                'unit_type' => 'AREA',
                'decimal_precision' => 3,
            ],
            [
                'code' => 'HOUR',
                'name' => 'Jam',
                'symbol' => 'jam',
                'unit_type' => 'TIME',
                'decimal_precision' => 2,
            ],
            [
                'code' => 'DAY',
                'name' => 'Hari',
                'symbol' => 'hari',
                'unit_type' => 'TIME',
                'decimal_precision' => 2,
            ],
            [
                'code' => 'PACKAGE',
                'name' => 'Paket',
                'symbol' => 'paket',
                'unit_type' => 'PACKAGE',
                'decimal_precision' => 0,
            ],
        ];

        foreach ($units as $unit) {
            $existing = DB::table('units')
                ->where('tenant_id', $tenantId)
                ->where('business_id', $businessId)
                ->where('code', $unit['code'])
                ->first();

            if ($existing) {
                continue;
            }

            DB::table('units')->insert([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'business_id' => $businessId,
                ...$unit,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
