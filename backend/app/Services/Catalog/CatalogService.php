<?php

namespace App\Services\Catalog;

use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\Unit;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CatalogService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function paginateItems(
        ?string $search = null,
        ?string $status = null,
        ?string $type = null,
        ?string $pricingMethod = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->itemQuery()
            ->when(
                $search,
                function (
                    Builder $query,
                    string $search
                ): void {
                    $query->where(
                        function (
                            Builder $query
                        ) use ($search): void {
                            $query
                                ->where(
                                    'name',
                                    'ILIKE',
                                    '%' . $search . '%'
                                )
                                ->orWhere(
                                    'code',
                                    'ILIKE',
                                    '%' . $search . '%'
                                );
                        }
                    );
                }
            )
            ->when(
                $status,
                fn (
                    Builder $query,
                    string $value
                ) => $query->where(
                    'status',
                    $value
                )
            )
            ->when(
                $type,
                fn (
                    Builder $query,
                    string $value
                ) => $query->where(
                    'type',
                    $value
                )
            )
            ->when(
                $pricingMethod,
                fn (
                    Builder $query,
                    string $value
                ) => $query->where(
                    'pricing_method',
                    $value
                )
            )
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findItemOrFail(
        string $itemId
    ): CatalogItem {
        return $this->itemQuery()
            ->where('id', $itemId)
            ->firstOrFail();
    }

    public function createItem(
        array $data
    ): CatalogItem {
        return CatalogItem::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $this->tenantContext->tenantId(),
            'category_id' =>
                $data['category_id'] ?? null,
            'unit_id' =>
                $data['unit_id'] ?? null,
            'type' => $data['type'],
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'description' =>
                $data['description'] ?? null,
            'pricing_method' =>
                $data['pricing_method']
                ?? 'STANDARD',
            'base_price' =>
                $data['base_price'] ?? 0,
            'currency' =>
                $data['currency'] ?? 'IDR',
            'pricing_config' =>
                $data['pricing_config'] ?? null,
            'status' => 'ACTIVE',
        ]);
    }

    public function updateItem(
        CatalogItem $item,
        array $data
    ): CatalogItem {
        $item->fill($data);
        $item->save();

        return $item->refresh();
    }

    public function categories(): array
    {
        return $this->categoryQuery()
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function findCategoryOrFail(
        string $categoryId
    ): CatalogCategory {
        return $this->categoryQuery()
            ->where('id', $categoryId)
            ->firstOrFail();
    }

    public function createCategory(
        array $data
    ): CatalogCategory {
        return CatalogCategory::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $this->tenantContext->tenantId(),
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'description' =>
                $data['description'] ?? null,
            'status' => 'ACTIVE',
        ]);
    }

    public function updateCategory(
        CatalogCategory $category,
        array $data
    ): CatalogCategory {
        $category->fill($data);
        $category->save();

        return $category->refresh();
    }

    public function findUnitOrFail(
        string $unitId
    ): Unit {
        return $this->unitQuery()
            ->where('id', $unitId)
            ->firstOrFail();
    }

    public function createUnit(
        array $data
    ): Unit {
        return Unit::query()->create([
            'id' => (string) Str::ulid(),

            'tenant_id' =>
                $this->tenantContext->tenantId(),

            'code' => $data['code'],

            'name' => $data['name'],

            'symbol' =>
                $data['symbol'] ?? null,

            'unit_type' =>
                $data['unit_type']
                ?? 'OTHER',

            'decimal_precision' =>
                $data['decimal_precision']
                ?? 2,

            'status' => 'ACTIVE',
        ]);
    }

    public function updateUnit(
        Unit $unit,
        array $data
    ): Unit {
        $unit->fill($data);
        $unit->save();

        return $unit->refresh();
    }

    public function units(): array
    {
        return $this->unitQuery()
            ->orderBy('name')
            ->get()
            ->all();
    }

    private function itemQuery(): Builder
    {
        return CatalogItem::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            );
    }

    private function categoryQuery(): Builder
    {
        return CatalogCategory::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            );
    }

    private function unitQuery(): Builder
    {
        return Unit::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            );
    }
}
