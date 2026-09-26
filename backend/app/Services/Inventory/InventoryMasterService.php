<?php

namespace App\Services\Inventory;

use App\Models\InventoryCategory;
use App\Models\Material;
use App\Models\Warehouse;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryMasterService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
    ) {
    }

    public function categories(): Collection
    {
        return InventoryCategory::query()
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

    public function createCategory(
        array $data
    ): InventoryCategory {
        $this->ensureCategoryNameAvailable(
            $data['name']
        );

        return InventoryCategory::query()
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

                'description' =>
                    $data['description']
                    ?? null,

                'status' =>
                    'ACTIVE',
            ]);
    }

    public function findCategoryOrFail(
        string $categoryId
    ): InventoryCategory {
        return InventoryCategory::query()
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
                $categoryId
            )
            ->firstOrFail();
    }

    public function updateCategory(
        InventoryCategory $category,
        array $data
    ): InventoryCategory {
        if (
            array_key_exists(
                'name',
                $data
            )
        ) {
            $this->ensureCategoryNameAvailable(
                $data['name'],
                $category->id
            );
        }

        return DB::transaction(
            function () use (
                $category,
                $data
            ): InventoryCategory {
                $oldName =
                    $category->name;

                $category->fill($data);
                $category->save();

                /*
                 * Legacy category string tetap disinkronkan.
                 * Source of truth tetap category_id.
                 */
                if (
                    array_key_exists(
                        'name',
                        $data
                    )
                    && $category->name
                        !== $oldName
                ) {
                    Material::query()
                        ->where(
                            'tenant_id',
                            $category->tenant_id
                        )
                        ->where(
                            'business_id',
                            $category->business_id
                        )
                        ->where(
                            'category_id',
                            $category->id
                        )
                        ->update([
                            'category' =>
                                $category->name,
                        ]);
                }

                return $category->refresh();
            }
        );
    }

    public function materials(): Collection
    {
        return Material::query()
            ->with(
                'inventoryCategory'
            )
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
        return DB::transaction(
            function () use ($data): Material {
                $categoryData =
                    $this->materialCategoryData(
                        $data
                    );

                $payload = [
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

                    ...$categoryData,

                    'inventory_type' =>
                        $data['inventory_type']
                        ?? 'RAW_MATERIAL',

                    'stock_tracking' =>
                        $data['stock_tracking']
                        ?? 'TRACKED',

                    'minimum_stock' =>
                        $data['minimum_stock']
                        ?? null,

                    'reorder_point' =>
                        $data['reorder_point']
                        ?? null,

                    'maximum_stock' =>
                        $data['maximum_stock']
                        ?? null,

                    'description' =>
                        $data['description']
                        ?? null,

                    'status' =>
                        'ACTIVE',
                ];

                $this->validateStockPolicy(
                    $payload
                );

                $material =
                    Material::query()
                        ->create($payload);

                return $material
                    ->load(
                        'inventoryCategory'
                    );
            }
        );
    }

    public function findMaterialOrFail(
        string $materialId
    ): Material {
        return Material::query()
            ->with(
                'inventoryCategory'
            )
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
        return DB::transaction(
            function () use (
                $material,
                $data
            ): Material {
                $categoryData =
                    $this->materialCategoryData(
                        $data,
                        true
                    );

                unset(
                    $data['category'],
                    $data['category_id']
                );

                if ($categoryData !== []) {
                    $data = [
                        ...$data,
                        ...$categoryData,
                    ];
                }

                $nextStockTracking =
                    $data['stock_tracking']
                    ?? $material->stock_tracking;

                if (
                    $nextStockTracking
                    === 'NOT_TRACKED'
                ) {
                    foreach (
                        [
                            'minimum_stock',
                            'reorder_point',
                            'maximum_stock',
                        ] as $field
                    ) {
                        if (
                            array_key_exists(
                                $field,
                                $data
                            )
                            && $data[$field]
                                !== null
                        ) {
                            throw ValidationException::withMessages([
                                $field =>
                                    'Batas stok hanya dapat digunakan untuk barang yang stoknya dilacak.',
                            ]);
                        }
                    }

                    /*
                     * Saat TRACKED → NOT_TRACKED,
                     * threshold lama dibersihkan.
                     */
                    if (
                        array_key_exists(
                            'stock_tracking',
                            $data
                        )
                    ) {
                        $data['minimum_stock'] =
                            null;

                        $data['reorder_point'] =
                            null;

                        $data['maximum_stock'] =
                            null;
                    }
                }

                $candidate = [
                    'stock_tracking' =>
                        $nextStockTracking,

                    'minimum_stock' =>
                        array_key_exists(
                            'minimum_stock',
                            $data
                        )
                            ? $data['minimum_stock']
                            : $material->minimum_stock,

                    'reorder_point' =>
                        array_key_exists(
                            'reorder_point',
                            $data
                        )
                            ? $data['reorder_point']
                            : $material->reorder_point,

                    'maximum_stock' =>
                        array_key_exists(
                            'maximum_stock',
                            $data
                        )
                            ? $data['maximum_stock']
                            : $material->maximum_stock,
                ];

                $this->validateStockPolicy(
                    $candidate
                );

                $material->fill($data);
                $material->save();

                return $material
                    ->refresh()
                    ->load(
                        'inventoryCategory'
                    );
            }
        );
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

    private function materialCategoryData(
        array $data,
        bool $onlyWhenProvided = false
    ): array {
        $categoryIdProvided =
            array_key_exists(
                'category_id',
                $data
            );

        $legacyProvided =
            array_key_exists(
                'category',
                $data
            );

        if (
            $categoryIdProvided
            && $data['category_id']
                !== null
            && $data['category_id']
                !== ''
        ) {
            $category =
                $this->findActiveCategoryOrFail(
                    (string) $data['category_id']
                );

            return [
                'category_id' =>
                    $category->id,

                'category' =>
                    $category->name,
            ];
        }

        if (
            $legacyProvided
            && trim(
                (string) (
                    $data['category']
                    ?? ''
                )
            ) !== ''
        ) {
            $category =
                $this->resolveLegacyCategory(
                    trim(
                        (string)
                            $data['category']
                    )
                );

            return [
                'category_id' =>
                    $category->id,

                'category' =>
                    $category->name,
            ];
        }

        if ($categoryIdProvided) {
            return [
                'category_id' =>
                    null,

                'category' =>
                    null,
            ];
        }

        if ($legacyProvided) {
            return [
                'category_id' =>
                    null,

                'category' =>
                    null,
            ];
        }

        return $onlyWhenProvided
            ? []
            : [
                'category_id' =>
                    null,

                'category' =>
                    null,
            ];
    }

    private function findActiveCategoryOrFail(
        string $categoryId
    ): InventoryCategory {
        return InventoryCategory::query()
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
                $categoryId
            )
            ->where(
                'status',
                'ACTIVE'
            )
            ->firstOrFail();
    }

    private function resolveLegacyCategory(
        string $name
    ): InventoryCategory {
        $existing =
            InventoryCategory::query()
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
                ->whereRaw(
                    'LOWER(BTRIM(name)) = LOWER(BTRIM(?))',
                    [$name]
                )
                ->first();

        if ($existing) {
            if (
                $existing->status
                !== 'ACTIVE'
            ) {
                throw ValidationException::withMessages([
                    'category' =>
                        'Kategori persediaan tersebut tidak aktif.',
                ]);
            }

            return $existing;
        }

        return InventoryCategory::query()
            ->create([
                'tenant_id' =>
                    $this->tenantContext
                        ->tenantId(),

                'business_id' =>
                    $this->businessContext
                        ->businessId(),

                'code' =>
                    $this->nextCategoryCode(
                        $name
                    ),

                'name' =>
                    $name,

                'description' =>
                    null,

                'status' =>
                    'ACTIVE',
            ]);
    }

    private function nextCategoryCode(
        string $name
    ): string {
        $baseCode =
            Str::upper(
                Str::slug(
                    $name,
                    '_'
                )
            );

        if ($baseCode === '') {
            $baseCode =
                'CATEGORY';
        }

        $baseCode =
            substr(
                $baseCode,
                0,
                68
            );

        $code =
            $baseCode;

        $sequence =
            1;

        while (
            InventoryCategory::query()
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
                    'code',
                    $code
                )
                ->exists()
        ) {
            $suffix =
                '_'.$sequence;

            $code =
                substr(
                    $baseCode,
                    0,
                    80 - strlen($suffix)
                )
                .$suffix;

            $sequence++;
        }

        return $code;
    }

    private function ensureCategoryNameAvailable(
        string $name,
        ?string $ignoreId = null
    ): void {
        $query =
            InventoryCategory::query()
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
                ->whereRaw(
                    'LOWER(BTRIM(name)) = LOWER(BTRIM(?))',
                    [$name]
                );

        if ($ignoreId !== null) {
            $query->where(
                'id',
                '!=',
                $ignoreId
            );
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' =>
                    'Nama kategori persediaan sudah digunakan.',
            ]);
        }
    }

    private function validateStockPolicy(
        array $data
    ): void {
        $stockTracking =
            $data['stock_tracking']
            ?? 'TRACKED';

        $minimum =
            $data['minimum_stock']
            ?? null;

        $reorder =
            $data['reorder_point']
            ?? null;

        $maximum =
            $data['maximum_stock']
            ?? null;

        if (
            $stockTracking
            === 'NOT_TRACKED'
            && (
                $minimum !== null
                || $reorder !== null
                || $maximum !== null
            )
        ) {
            throw ValidationException::withMessages([
                'stock_tracking' =>
                    'Barang yang stoknya tidak dilacak tidak menggunakan batas stok.',
            ]);
        }

        if (
            $minimum !== null
            && $reorder !== null
            && (float) $minimum
                > (float) $reorder
        ) {
            throw ValidationException::withMessages([
                'reorder_point' =>
                    'Titik pesan ulang tidak boleh lebih kecil dari stok minimum.',
            ]);
        }

        if (
            $reorder !== null
            && $maximum !== null
            && (float) $reorder
                > (float) $maximum
        ) {
            throw ValidationException::withMessages([
                'maximum_stock' =>
                    'Stok maksimum tidak boleh lebih kecil dari titik pesan ulang.',
            ]);
        }

        if (
            $minimum !== null
            && $maximum !== null
            && (float) $minimum
                > (float) $maximum
        ) {
            throw ValidationException::withMessages([
                'maximum_stock' =>
                    'Stok maksimum tidak boleh lebih kecil dari stok minimum.',
            ]);
        }
    }
}
