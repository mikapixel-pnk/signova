<?php

namespace App\Services\Inventory;

use App\Models\Material;
use App\Models\Warehouse;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;

class InventoryMasterService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
    ) {
    }

    public function materials(): Collection
    {
        return Material::query()
            ->where(
                'tenant_id',
                $this->tenantContext
                    ->tenantId()
            )
            ->where(
                'business_id',
                $this->businessContext
                    ->businessId()
            )
            ->orderBy('name')
            ->get();
    }

    public function createMaterial(
        array $data
    ): Material {
        return Material::query()
            ->create([
                'tenant_id' =>
                    $this->tenantContext
                        ->tenantId(),

                'business_id' =>
                    $this->businessContext
                        ->businessId(),

                'code' =>
                    $data['code'],

                'name' =>
                    $data['name'],

                'unit_id' =>
                    $data['unit_id']
                    ?? null,

                'category' =>
                    $data['category']
                    ?? null,

                'status' =>
                    'ACTIVE',
            ]);
    }

    public function findMaterialOrFail(
        string $materialId
    ): Material {
        return Material::query()
            ->where(
                'tenant_id',
                $this->tenantContext
                    ->tenantId()
            )
            ->where(
                'business_id',
                $this->businessContext
                    ->businessId()
            )
            ->where(
                'id',
                $materialId
            )
            ->firstOrFail();
    }

    public function updateMaterial(
        Material $material,
        array $data
    ): Material {
        $material->fill($data);
        $material->save();

        return $material->refresh();
    }

    public function warehouses(): Collection
    {
        return Warehouse::query()
            ->where(
                'tenant_id',
                $this->tenantContext
                    ->tenantId()
            )
            ->where(
                'business_id',
                $this->businessContext
                    ->businessId()
            )
            ->orderBy('name')
            ->get();
    }

    public function createWarehouse(
        array $data
    ): Warehouse {
        return Warehouse::query()
            ->create([
                'tenant_id' =>
                    $this->tenantContext
                        ->tenantId(),

                'business_id' =>
                    $this->businessContext
                        ->businessId(),

                'name' =>
                    $data['name'],

                'location' =>
                    $data['location']
                    ?? null,

                'status' =>
                    'ACTIVE',
            ]);
    }

    public function findWarehouseOrFail(
        string $warehouseId
    ): Warehouse {
        return Warehouse::query()
            ->where(
                'tenant_id',
                $this->tenantContext
                    ->tenantId()
            )
            ->where(
                'business_id',
                $this->businessContext
                    ->businessId()
            )
            ->where(
                'id',
                $warehouseId
            )
            ->firstOrFail();
    }

    public function updateWarehouse(
        Warehouse $warehouse,
        array $data
    ): Warehouse {
        $warehouse->fill($data);
        $warehouse->save();

        return $warehouse->refresh();
    }
}
