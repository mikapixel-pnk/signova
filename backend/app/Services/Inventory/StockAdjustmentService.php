<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\StockAdjustmentStateConflictException;
use App\Models\Material;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockAdjustmentStatusHistory;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Document\DocumentNumberService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly DocumentNumberService $documentNumberService,
    ) {
    }

    public function all(): Collection
    {
        return StockAdjustment::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            )
            ->where(
                'business_id',
                $this->businessContext->businessId()
            )
            ->with([
                'warehouse',
                'items.material',
            ])
            ->latest('created_at')
            ->get();
    }

    public function findOrFail(
        string $stockAdjustmentId
    ): StockAdjustment {
        return StockAdjustment::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            )
            ->where(
                'business_id',
                $this->businessContext->businessId()
            )
            ->where(
                'id',
                $stockAdjustmentId
            )
            ->with([
                'warehouse',
                'items.material',
            ])
            ->firstOrFail();
    }

    public function createDraft(
        array $data
    ): StockAdjustment {
        return DB::transaction(
            function () use ($data): StockAdjustment {
                $warehouse =
                    $this->activeWarehouse(
                        (string) $data['warehouse_id']
                    );

                $reason =
                    $this->reason(
                        $data['reason'] ?? null,
                        'reason'
                    );

                $adjustment =
                    StockAdjustment::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $this->tenantContext
                                    ->tenantId(),

                            'business_id' =>
                                $this->businessContext
                                    ->businessId(),

                            'adjustment_number' =>
                                $this
                                    ->documentNumberService
                                    ->nextStockAdjustmentNumber(),

                            'warehouse_id' =>
                                $warehouse->id,

                            'status' =>
                                'DRAFT',

                            'reason' =>
                                $reason,

                            'notes' =>
                                $this->optionalText(
                                    $data['notes']
                                    ?? null
                                ),

                            'adjusted_at' =>
                                $data['adjusted_at']
                                ?? null,

                            'created_by_user_id' =>
                                $this->tenantContext
                                    ->userId(),
                        ]);

                $this->replaceItems(
                    $adjustment,
                    $data['items'] ?? []
                );

                $this->appendHistory(
                    $adjustment,
                    null,
                    'DRAFT',
                    'CREATED',
                    $reason
                );

                return $this->findOrFail(
                    $adjustment->id
                );
            }
        );
    }

    public function updateDraft(
        string $stockAdjustmentId,
        array $data
    ): StockAdjustment {
        return DB::transaction(
            function () use (
                $stockAdjustmentId,
                $data
            ): StockAdjustment {
                $adjustment =
                    $this->lockedAdjustment(
                        $stockAdjustmentId
                    );

                if (
                    $adjustment->status
                    !== 'DRAFT'
                ) {
                    throw new StockAdjustmentStateConflictException(
                        'Penyesuaian Stok hanya dapat diubah saat masih Draf.'
                    );
                }

                if (
                    array_key_exists(
                        'warehouse_id',
                        $data
                    )
                ) {
                    $adjustment->warehouse_id =
                        $this->activeWarehouse(
                            (string)
                                $data['warehouse_id']
                        )->id;
                }

                if (
                    array_key_exists(
                        'reason',
                        $data
                    )
                ) {
                    $adjustment->reason =
                        $this->reason(
                            $data['reason'],
                            'reason'
                        );
                }

                if (
                    array_key_exists(
                        'notes',
                        $data
                    )
                ) {
                    $adjustment->notes =
                        $this->optionalText(
                            $data['notes']
                        );
                }

                if (
                    array_key_exists(
                        'adjusted_at',
                        $data
                    )
                ) {
                    $adjustment->adjusted_at =
                        $data['adjusted_at'];
                }

                $adjustment->save();

                if (
                    array_key_exists(
                        'items',
                        $data
                    )
                ) {
                    $this->replaceItems(
                        $adjustment,
                        $data['items']
                    );
                }

                return $this->findOrFail(
                    $adjustment->id
                );
            }
        );
    }

    public function post(
        string $stockAdjustmentId
    ): StockAdjustment {
        return DB::transaction(
            function () use (
                $stockAdjustmentId
            ): StockAdjustment {
                $adjustment =
                    $this->lockedAdjustment(
                        $stockAdjustmentId
                    );

                if (
                    $adjustment->status
                    !== 'DRAFT'
                ) {
                    throw new StockAdjustmentStateConflictException(
                        'Hanya Penyesuaian Stok berstatus Draf yang dapat dicatat.'
                    );
                }

                $warehouse =
                    $this->activeWarehouse(
                        $adjustment->warehouse_id
                    );

                $items =
                    StockAdjustmentItem::query()
                        ->where(
                            'tenant_id',
                            $adjustment->tenant_id
                        )
                        ->where(
                            'business_id',
                            $adjustment->business_id
                        )
                        ->where(
                            'stock_adjustment_id',
                            $adjustment->id
                        )
                        ->lockForUpdate()
                        ->get();

                if ($items->isEmpty()) {
                    throw new StockAdjustmentStateConflictException(
                        'Penyesuaian Stok harus memiliki minimal satu item.'
                    );
                }

                $occurredAt =
                    $adjustment->adjusted_at
                    ?? now();

                foreach (
                    $items as $index => $item
                ) {
                    $material =
                        $this->activeTrackedMaterial(
                            $item->material_id,
                            "items.{$index}.material_id"
                        );

                    $quantity =
                        $this->quantityDelta(
                            $item->quantity_delta,
                            "items.{$index}.quantity_delta"
                        );

                    StockMovement::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $adjustment->tenant_id,

                            'business_id' =>
                                $adjustment->business_id,

                            'material_id' =>
                                $material->id,

                            'warehouse_id' =>
                                $warehouse->id,

                            'type' =>
                                'ADJUSTMENT',

                            'quantity_signed' =>
                                (string) $quantity,

                            'source_type' =>
                                'STOCK_ADJUSTMENT_ITEM',

                            'source_id' =>
                                $item->id,

                            'occurred_at' =>
                                $occurredAt,

                            'actor_user_id' =>
                                $this->tenantContext
                                    ->userId(),

                            'reason' =>
                                $adjustment->reason,

                            'reversal_of_movement_id' =>
                                null,

                            'created_at' =>
                                now(),
                        ]);
                }

                $from =
                    $adjustment->status;

                $adjustment->status =
                    'POSTED';

                $adjustment->adjusted_at =
                    $occurredAt;

                $adjustment->posted_by_user_id =
                    $this->tenantContext
                        ->userId();

                $adjustment->posted_at =
                    now();

                $adjustment->save();

                $this->appendHistory(
                    $adjustment,
                    $from,
                    'POSTED',
                    'POSTED',
                    $adjustment->reason
                );

                return $this->findOrFail(
                    $adjustment->id
                );
            }
        );
    }

    public function reverse(
        string $stockAdjustmentId,
        string $reason
    ): StockAdjustment {
        return DB::transaction(
            function () use (
                $stockAdjustmentId,
                $reason
            ): StockAdjustment {
                $adjustment =
                    $this->lockedAdjustment(
                        $stockAdjustmentId
                    );

                if (
                    $adjustment->status
                    !== 'POSTED'
                ) {
                    throw new StockAdjustmentStateConflictException(
                        'Hanya Penyesuaian Stok yang sudah Dicatat yang dapat dikoreksi.'
                    );
                }

                $reversalReason =
                    $this->reason(
                        $reason,
                        'reason'
                    );

                $itemIds =
                    StockAdjustmentItem::query()
                        ->where(
                            'tenant_id',
                            $adjustment->tenant_id
                        )
                        ->where(
                            'business_id',
                            $adjustment->business_id
                        )
                        ->where(
                            'stock_adjustment_id',
                            $adjustment->id
                        )
                        ->pluck('id');

                if ($itemIds->isEmpty()) {
                    throw new StockAdjustmentStateConflictException(
                        'Penyesuaian Stok tidak memiliki item untuk dikoreksi.'
                    );
                }

                $movements =
                    StockMovement::query()
                        ->where(
                            'tenant_id',
                            $adjustment->tenant_id
                        )
                        ->where(
                            'business_id',
                            $adjustment->business_id
                        )
                        ->where(
                            'type',
                            'ADJUSTMENT'
                        )
                        ->where(
                            'source_type',
                            'STOCK_ADJUSTMENT_ITEM'
                        )
                        ->whereIn(
                            'source_id',
                            $itemIds
                        )
                        ->lockForUpdate()
                        ->get();

                if (
                    $movements->count()
                    !== $itemIds->count()
                ) {
                    throw new StockAdjustmentStateConflictException(
                        'Ledger Penyesuaian Stok tidak lengkap sehingga koreksi tidak dapat dilanjutkan.'
                    );
                }

                foreach ($movements as $movement) {
                    $quantity =
                        $this->quantityDelta(
                            $movement
                                ->quantity_signed,
                            'quantity_signed'
                        );

                    StockMovement::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $movement->tenant_id,

                            'business_id' =>
                                $movement->business_id,

                            'material_id' =>
                                $movement->material_id,

                            'warehouse_id' =>
                                $movement->warehouse_id,

                            'type' =>
                                'ADJUSTMENT_REVERSAL',

                            'quantity_signed' =>
                                (string)
                                    $quantity
                                        ->multipliedBy(
                                            '-1'
                                        )
                                        ->toScale(4),

                            'source_type' =>
                                $movement->source_type,

                            'source_id' =>
                                $movement->source_id,

                            'occurred_at' =>
                                now(),

                            'actor_user_id' =>
                                $this->tenantContext
                                    ->userId(),

                            'reason' =>
                                $reversalReason,

                            'reversal_of_movement_id' =>
                                $movement->id,

                            'created_at' =>
                                now(),
                        ]);
                }

                $from =
                    $adjustment->status;

                $adjustment->status =
                    'REVERSED';

                $adjustment->reversed_by_user_id =
                    $this->tenantContext
                        ->userId();

                $adjustment->reversed_at =
                    now();

                $adjustment->reversal_reason =
                    $reversalReason;

                $adjustment->save();

                $this->appendHistory(
                    $adjustment,
                    $from,
                    'REVERSED',
                    'REVERSED',
                    $reversalReason
                );

                return $this->findOrFail(
                    $adjustment->id
                );
            }
        );
    }

    private function replaceItems(
        StockAdjustment $adjustment,
        array $items
    ): void {
        if (
            count($items) < 1
            || count($items) > 100
        ) {
            throw ValidationException::withMessages([
                'items' =>
                    'Penyesuaian Stok harus memiliki 1 sampai 100 item.',
            ]);
        }

        $seenMaterialIds = [];

        StockAdjustmentItem::query()
            ->where(
                'tenant_id',
                $adjustment->tenant_id
            )
            ->where(
                'business_id',
                $adjustment->business_id
            )
            ->where(
                'stock_adjustment_id',
                $adjustment->id
            )
            ->delete();

        foreach ($items as $index => $item) {
            $materialId =
                trim(
                    (string) (
                        $item['material_id']
                        ?? ''
                    )
                );

            if ($materialId === '') {
                throw ValidationException::withMessages([
                    "items.{$index}.material_id" =>
                        'Barang Persediaan wajib dipilih.',
                ]);
            }

            if (
                isset(
                    $seenMaterialIds[
                        $materialId
                    ]
                )
            ) {
                throw ValidationException::withMessages([
                    "items.{$index}.material_id" =>
                        'Barang Persediaan yang sama tidak boleh dicatat dua kali.',
                ]);
            }

            $seenMaterialIds[
                $materialId
            ] = true;

            $material =
                $this->activeTrackedMaterial(
                    $materialId,
                    "items.{$index}.material_id"
                );

            $quantity =
                $this->quantityDelta(
                    $item['quantity_delta']
                    ?? null,
                    "items.{$index}.quantity_delta"
                );

            StockAdjustmentItem::query()
                ->create([
                    'id' =>
                        (string) Str::ulid(),

                    'tenant_id' =>
                        $adjustment->tenant_id,

                    'business_id' =>
                        $adjustment->business_id,

                    'stock_adjustment_id' =>
                        $adjustment->id,

                    'material_id' =>
                        $material->id,

                    'quantity_delta' =>
                        (string) $quantity,

                    'notes' =>
                        $this->optionalText(
                            $item['notes']
                            ?? null
                        ),
                ]);
        }
    }

    private function lockedAdjustment(
        string $stockAdjustmentId
    ): StockAdjustment {
        return StockAdjustment::query()
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
                $stockAdjustmentId
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function activeWarehouse(
        string $warehouseId
    ): Warehouse {
        $warehouse =
            Warehouse::query()
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
                ->where(
                    'status',
                    'ACTIVE'
                )
                ->first();

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' =>
                    'Gudang tidak ditemukan atau tidak aktif.',
            ]);
        }

        return $warehouse;
    }

    private function activeTrackedMaterial(
        string $materialId,
        string $field
    ): Material {
        $material =
            Material::query()
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
                ->where(
                    'status',
                    'ACTIVE'
                )
                ->first();

        if (! $material) {
            throw ValidationException::withMessages([
                $field =>
                    'Barang Persediaan tidak ditemukan atau tidak aktif.',
            ]);
        }

        if (
            strtoupper(
                (string)
                    $material->stock_tracking
            )
            !== 'TRACKED'
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'Hanya Barang Persediaan yang dilacak stoknya yang dapat disesuaikan.',
            ]);
        }

        return $material;
    }

    private function quantityDelta(
        mixed $value,
        string $field
    ): BigDecimal {
        $raw =
            trim(
                (string) $value
            );

        /*
         * decimal(18,4):
         * maksimal 14 digit sebelum koma
         * dan maksimal 4 digit pecahan.
         *
         * Tidak ada float dan tidak ada
         * silent rounding pada write path.
         */
        if (
            ! preg_match(
                '/^-?\d{1,14}(?:\.\d{1,4})?$/',
                $raw
            )
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'Jumlah penyesuaian harus berupa angka dengan maksimal 4 angka di belakang koma.',
            ]);
        }

        $quantity =
            BigDecimal::of(
                $raw
            )->toScale(4);

        if ($quantity->isZero()) {
            throw ValidationException::withMessages([
                $field =>
                    'Jumlah penyesuaian tidak boleh nol.',
            ]);
        }

        return $quantity;
    }

    private function reason(
        mixed $value,
        string $field
    ): string {
        $reason =
            is_string($value)
                ? trim($value)
                : '';

        if (
            mb_strlen($reason) < 3
            || mb_strlen($reason) > 5000
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'Alasan wajib diisi minimal 3 karakter.',
            ]);
        }

        return $reason;
    }

    private function optionalText(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $text =
            trim(
                (string) $value
            );

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > 5000) {
            throw ValidationException::withMessages([
                'notes' =>
                    'Catatan maksimal 5000 karakter.',
            ]);
        }

        return $text;
    }

    private function appendHistory(
        StockAdjustment $adjustment,
        ?string $from,
        string $to,
        string $action,
        ?string $reason = null
    ): void {
        StockAdjustmentStatusHistory::query()
            ->create([
                'id' =>
                    (string) Str::ulid(),

                'tenant_id' =>
                    $adjustment->tenant_id,

                'business_id' =>
                    $adjustment->business_id,

                'stock_adjustment_id' =>
                    $adjustment->id,

                'from_status' =>
                    $from,

                'to_status' =>
                    $to,

                'action' =>
                    $action,

                'actor_user_id' =>
                    $this->tenantContext
                        ->userId(),

                'reason' =>
                    $reason,

                'created_at' =>
                    now(),
            ]);
    }
}
