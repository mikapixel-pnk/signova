<?php

namespace App\Services\Inventory;

use App\Models\Material;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class InventoryStockReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
    ) {}

    public function balances(): array
    {
        $tenantId =
            $this->tenantContext
                ->tenantId();

        $businessId =
            $this->businessContext
                ->businessId();

        $totals =
            DB::table(
                'stock_movements'
            )
                ->select(
                    'material_id'
                )
                ->selectRaw(
                    'CAST(
                        SUM(quantity_signed)
                        AS DECIMAL(18, 4)
                    ) AS on_hand'
                )
                ->where(
                    'tenant_id',
                    $tenantId
                )
                ->where(
                    'business_id',
                    $businessId
                )
                ->groupBy(
                    'material_id'
                )
                ->get()
                ->keyBy(
                    'material_id'
                );

        return Material::query()
            ->with(
                'inventoryCategory'
            )
            ->where(
                'tenant_id',
                $tenantId
            )
            ->where(
                'business_id',
                $businessId
            )
            ->where(
                'stock_tracking',
                'TRACKED'
            )
            ->orderBy(
                'name'
            )
            ->get()
            ->map(
                function (
                    Material $material
                ) use ($totals): array {
                    $total =
                        $totals->get(
                            $material->id
                        );

                    return [
                        'material_id' => $material->id,

                        'code' => $material->code,

                        'name' => $material->name,

                        'unit_id' => $material->unit_id,

                        'category_id' => $material->category_id,

                        'category' => $material
                            ->inventoryCategory
                            ?->name
                            ?? $material->category,

                        'inventory_type' => $material
                            ->inventory_type,

                        'status' => $material->status,

                        'minimum_stock' => $material
                            ->minimum_stock,

                        'reorder_point' => $material
                            ->reorder_point,

                        'maximum_stock' => $material
                            ->maximum_stock,

                        /*
                         * Source of truth tetap ledger.
                         * Material tanpa movement tetap memiliki
                         * saldo awal read-model 0.0000.
                         */
                        'on_hand' => $total
                                ? (string) $total
                                    ->on_hand
                                : '0.0000',
                    ];
                }
            )
            ->values()
            ->all();
    }
}
