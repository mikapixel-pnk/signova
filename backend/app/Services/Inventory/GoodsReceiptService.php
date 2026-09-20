<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\GoodsReceiptStateConflictException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\GoodsReceiptStatusHistory;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Document\DocumentNumberService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    private const EPSILON = 0.00005;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly DocumentNumberService $documentNumberService,
    ) {
    }

    public function all(
        ?string $purchaseOrderId = null
    ): Collection {
        return GoodsReceipt::query()
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
            ->when(
                $purchaseOrderId,
                fn ($query) =>
                    $query->where(
                        'purchase_order_id',
                        $purchaseOrderId
                    )
            )
            ->with([
                'warehouse',
                'items.purchaseOrderItem',
                'items.material',
            ])
            ->latest('created_at')
            ->get();
    }

    public function findOrFail(
        string $goodsReceiptId
    ): GoodsReceipt {
        return GoodsReceipt::query()
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
                $goodsReceiptId
            )
            ->with([
                'warehouse',
                'purchaseOrder',
                'items.purchaseOrderItem',
                'items.material',
            ])
            ->firstOrFail();
    }

    public function createDraft(
        array $data
    ): GoodsReceipt {
        return DB::transaction(
            function () use ($data): GoodsReceipt {
                $purchaseOrder =
                    $this->lockedPurchaseOrder(
                        $data[
                            'purchase_order_id'
                        ]
                    );

                if (
                    ! in_array(
                        $purchaseOrder->status,
                        [
                            'ISSUED',
                            'PARTIALLY_RECEIVED',
                        ],
                        true
                    )
                ) {
                    throw new GoodsReceiptStateConflictException(
                        'Penerimaan hanya dapat dibuat dari Pesanan Pembelian yang sudah diterbitkan dan masih memiliki barang/jasa yang belum diterima.'
                    );
                }

                $warehouse =
                    $this->activeWarehouse(
                        $data['warehouse_id']
                    );

                $receipt =
                    GoodsReceipt::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $purchaseOrder
                                    ->tenant_id,

                            'business_id' =>
                                $purchaseOrder
                                    ->business_id,

                            'purchase_order_id' =>
                                $purchaseOrder->id,

                            'receipt_number' =>
                                $this
                                    ->documentNumberService
                                    ->nextGoodsReceiptNumber(),

                            'status' =>
                                'DRAFT',

                            'warehouse_id' =>
                                $warehouse->id,

                            'received_at' =>
                                $data['received_at']
                                ?? null,

                            'notes' =>
                                $data['notes']
                                ?? null,

                            'created_by_user_id' =>
                                $this
                                    ->tenantContext
                                    ->userId(),
                        ]);

                $items =
                    $data['items']
                    ?? $this->remainingItems(
                        $purchaseOrder
                    );

                if ($items === []) {
                    throw new GoodsReceiptStateConflictException(
                        'Semua item Pesanan Pembelian sudah diterima.'
                    );
                }

                $this->replaceItems(
                    $receipt,
                    $purchaseOrder,
                    $items
                );

                $this->appendReceiptHistory(
                    $receipt,
                    null,
                    'DRAFT',
                    'CREATED'
                );

                return $this->findOrFail(
                    $receipt->id
                );
            }
        );
    }

    public function updateDraft(
        string $goodsReceiptId,
        array $data
    ): GoodsReceipt {
        return DB::transaction(
            function () use (
                $goodsReceiptId,
                $data
            ): GoodsReceipt {
                $receipt =
                    $this->lockedReceipt(
                        $goodsReceiptId
                    );

                if (
                    $receipt->status
                    !== 'DRAFT'
                ) {
                    throw new GoodsReceiptStateConflictException(
                        'Penerimaan hanya dapat diubah saat masih Draf.'
                    );
                }

                $purchaseOrder =
                    $this->lockedPurchaseOrder(
                        $receipt
                            ->purchase_order_id
                    );

                if (
                    array_key_exists(
                        'warehouse_id',
                        $data
                    )
                ) {
                    $receipt->warehouse_id =
                        $this
                            ->activeWarehouse(
                                $data[
                                    'warehouse_id'
                                ]
                            )
                            ->id;
                }

                foreach (
                    [
                        'received_at',
                        'notes',
                    ] as $field
                ) {
                    if (
                        array_key_exists(
                            $field,
                            $data
                        )
                    ) {
                        $receipt->{$field} =
                            $data[$field];
                    }
                }

                $receipt->save();

                if (
                    array_key_exists(
                        'items',
                        $data
                    )
                ) {
                    $this->replaceItems(
                        $receipt,
                        $purchaseOrder,
                        $data['items']
                    );
                }

                return $this->findOrFail(
                    $receipt->id
                );
            }
        );
    }

    public function post(
        string $goodsReceiptId
    ): GoodsReceipt {
        return DB::transaction(
            function () use (
                $goodsReceiptId
            ): GoodsReceipt {
                $receipt =
                    $this->lockedReceipt(
                        $goodsReceiptId
                    );

                if (
                    $receipt->status
                    !== 'DRAFT'
                ) {
                    throw new GoodsReceiptStateConflictException(
                        'Hanya Penerimaan berstatus Draf yang dapat dicatat.'
                    );
                }

                $purchaseOrder =
                    $this->lockedPurchaseOrder(
                        $receipt
                            ->purchase_order_id
                    );

                if (
                    ! in_array(
                        $purchaseOrder->status,
                        [
                            'ISSUED',
                            'PARTIALLY_RECEIVED',
                        ],
                        true
                    )
                ) {
                    throw new GoodsReceiptStateConflictException(
                        'Pesanan Pembelian sudah tidak dapat menerima pencatatan baru.'
                    );
                }

                $warehouse =
                    $this->activeWarehouse(
                        $receipt->warehouse_id
                    );

                $receiptItems =
                    GoodsReceiptItem::query()
                        ->where(
                            'goods_receipt_id',
                            $receipt->id
                        )
                        ->lockForUpdate()
                        ->get();

                if ($receiptItems->isEmpty()) {
                    throw new GoodsReceiptStateConflictException(
                        'Penerimaan harus memiliki minimal satu item.'
                    );
                }

                $poItems =
                    PurchaseOrderItem::query()
                        ->where(
                            'tenant_id',
                            $receipt->tenant_id
                        )
                        ->where(
                            'business_id',
                            $receipt->business_id
                        )
                        ->where(
                            'purchase_order_id',
                            $purchaseOrder->id
                        )
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                foreach (
                    $receiptItems as $index => $item
                ) {
                    $poItem =
                        $poItems->get(
                            $item
                                ->purchase_order_item_id
                        );

                    if (! $poItem) {
                        throw ValidationException::withMessages([
                            "items.{$index}.purchase_order_item_id" =>
                                'Item tidak termasuk dalam Pesanan Pembelian ini.',
                        ]);
                    }

                    $alreadyPosted =
                        $this->postedQuantity(
                            $poItem->id
                        );

                    $newTotal =
                        $alreadyPosted
                        + (float)
                            $item
                                ->quantity_received;

                    if (
                        $newTotal
                        >
                        (float) $poItem->quantity
                        + self::EPSILON
                    ) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity_received" =>
                                'Jumlah penerimaan melebihi sisa jumlah pada Pesanan Pembelian.',
                        ]);
                    }

                    $itemType =
                        strtoupper(
                            (string) (
                                $poItem->item_type
                                ?: 'PRODUCT'
                            )
                        );

                    if (
                        $itemType
                        === 'SERVICE'
                    ) {
                        if ($item->material_id) {
                            throw ValidationException::withMessages([
                                "items.{$index}.material_id" =>
                                    'Item jasa tidak menghasilkan pergerakan stok.',
                            ]);
                        }

                        continue;
                    }

                    if (! $item->material_id) {
                        throw ValidationException::withMessages([
                            "items.{$index}.material_id" =>
                                'Material wajib dipilih untuk item barang sebelum penerimaan dicatat.',
                        ]);
                    }

                    $material =
                        $this->activeMaterial(
                            $item->material_id
                        );

                    if (
                        $material->unit_id
                        && $poItem->unit_id
                        && $material->unit_id
                            !== $poItem->unit_id
                    ) {
                        throw ValidationException::withMessages([
                            "items.{$index}.material_id" =>
                                'Satuan material tidak sesuai dengan satuan item Pesanan Pembelian.',
                        ]);
                    }

                    StockMovement::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $receipt->tenant_id,

                            'business_id' =>
                                $receipt->business_id,

                            'material_id' =>
                                $material->id,

                            'warehouse_id' =>
                                $warehouse->id,

                            'type' =>
                                'RECEIPT',

                            'quantity_signed' =>
                                $item
                                    ->quantity_received,

                            'source_type' =>
                                'GOODS_RECEIPT_ITEM',

                            'source_id' =>
                                $item->id,

                            'occurred_at' =>
                                $receipt->received_at
                                ?? now(),

                            'actor_user_id' =>
                                $this
                                    ->tenantContext
                                    ->userId(),

                            'reason' =>
                                null,

                            'reversal_of_movement_id' =>
                                null,

                            'created_at' =>
                                now(),
                        ]);
                }

                $from =
                    $receipt->status;

                $receipt->status =
                    'POSTED';

                $receipt->received_at =
                    $receipt->received_at
                    ?? now();

                $receipt->posted_by_user_id =
                    $this
                        ->tenantContext
                        ->userId();

                $receipt->posted_at =
                    now();

                $receipt->save();

                $this->appendReceiptHistory(
                    $receipt,
                    $from,
                    'POSTED',
                    'POSTED'
                );

                $this->recalculatePurchaseOrder(
                    $purchaseOrder,
                    'RECEIPT_POSTED'
                );

                return $this->findOrFail(
                    $receipt->id
                );
            }
        );
    }

    public function reverse(
        string $goodsReceiptId,
        string $reason
    ): GoodsReceipt {
        return DB::transaction(
            function () use (
                $goodsReceiptId,
                $reason
            ): GoodsReceipt {
                $receipt =
                    $this->lockedReceipt(
                        $goodsReceiptId
                    );

                if (
                    $receipt->status
                    !== 'POSTED'
                ) {
                    throw new GoodsReceiptStateConflictException(
                        'Hanya Penerimaan yang sudah Dicatat yang dapat dikoreksi.'
                    );
                }

                $purchaseOrder =
                    $this->lockedPurchaseOrder(
                        $receipt
                            ->purchase_order_id
                    );

                $hasSupplierBill =
                    DB::table(
                        'supplier_bills'
                    )
                        ->where(
                            'tenant_id',
                            $receipt->tenant_id
                        )
                        ->where(
                            'business_id',
                            $receipt->business_id
                        )
                        ->where(
                            'goods_receipt_id',
                            $receipt->id
                        )
                        ->where(
                            'status',
                            '<>',
                            'CANCELLED'
                        )
                        ->exists();

                if ($hasSupplierBill) {
                    throw new GoodsReceiptStateConflictException(
                        'Penerimaan yang sudah memiliki Tagihan Pemasok aktif tidak dapat dikoreksi. Batalkan Tagihan Pemasok terlebih dahulu.'
                    );
                }

                $receiptItemIds =
                    GoodsReceiptItem::query()
                        ->where(
                            'goods_receipt_id',
                            $receipt->id
                        )
                        ->pluck('id');

                $movements =
                    StockMovement::query()
                        ->where(
                            'tenant_id',
                            $receipt->tenant_id
                        )
                        ->where(
                            'business_id',
                            $receipt->business_id
                        )
                        ->where(
                            'type',
                            'RECEIPT'
                        )
                        ->where(
                            'source_type',
                            'GOODS_RECEIPT_ITEM'
                        )
                        ->whereIn(
                            'source_id',
                            $receiptItemIds
                        )
                        ->lockForUpdate()
                        ->get();

                foreach ($movements as $movement) {
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
                                'RECEIPT_REVERSAL',

                            'quantity_signed' =>
                                -1
                                * (float)
                                    $movement
                                        ->quantity_signed,

                            'source_type' =>
                                $movement->source_type,

                            'source_id' =>
                                $movement->source_id,

                            'occurred_at' =>
                                now(),

                            'actor_user_id' =>
                                $this
                                    ->tenantContext
                                    ->userId(),

                            'reason' =>
                                trim($reason),

                            'reversal_of_movement_id' =>
                                $movement->id,

                            'created_at' =>
                                now(),
                        ]);
                }

                $from =
                    $receipt->status;

                $receipt->status =
                    'REVERSED';

                $receipt->reversed_by_user_id =
                    $this
                        ->tenantContext
                        ->userId();

                $receipt->reversed_at =
                    now();

                $receipt->reversal_reason =
                    trim($reason);

                $receipt->save();

                $this->appendReceiptHistory(
                    $receipt,
                    $from,
                    'REVERSED',
                    'REVERSED',
                    trim($reason)
                );

                $this->recalculatePurchaseOrder(
                    $purchaseOrder,
                    'RECEIPT_REVERSED',
                    trim($reason)
                );

                return $this->findOrFail(
                    $receipt->id
                );
            }
        );
    }

    private function replaceItems(
        GoodsReceipt $receipt,
        PurchaseOrder $purchaseOrder,
        array $items
    ): void {
        GoodsReceiptItem::query()
            ->where(
                'goods_receipt_id',
                $receipt->id
            )
            ->delete();

        foreach (
            array_values($items)
            as $index => $data
        ) {
            $poItem =
                PurchaseOrderItem::query()
                    ->where(
                        'tenant_id',
                        $receipt->tenant_id
                    )
                    ->where(
                        'business_id',
                        $receipt->business_id
                    )
                    ->where(
                        'purchase_order_id',
                        $purchaseOrder->id
                    )
                    ->where(
                        'id',
                        $data[
                            'purchase_order_item_id'
                        ]
                    )
                    ->firstOrFail();

            $quantity =
                (float)
                    $data[
                        'quantity_received'
                    ];

            $remaining =
                (float) $poItem->quantity
                - $this->postedQuantity(
                    $poItem->id
                );

            if (
                $quantity
                >
                $remaining
                + self::EPSILON
            ) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity_received" =>
                        'Jumlah penerimaan melebihi sisa jumlah pada Pesanan Pembelian.',
                ]);
            }

            $materialId =
                $data['material_id']
                ?? null;

            $itemType =
                strtoupper(
                    (string) (
                        $poItem->item_type
                        ?: 'PRODUCT'
                    )
                );

            if (
                $itemType === 'SERVICE'
                && $materialId
            ) {
                throw ValidationException::withMessages([
                    "items.{$index}.material_id" =>
                        'Item jasa tidak menggunakan material stok.',
                ]);
            }

            if ($materialId) {
                $material =
                    $this->activeMaterial(
                        $materialId
                    );

                if (
                    $material->unit_id
                    && $poItem->unit_id
                    && $material->unit_id
                        !== $poItem->unit_id
                ) {
                    throw ValidationException::withMessages([
                        "items.{$index}.material_id" =>
                            'Satuan material tidak sesuai dengan satuan item Pesanan Pembelian.',
                    ]);
                }
            }

            GoodsReceiptItem::query()
                ->create([
                    'id' =>
                        (string) Str::ulid(),

                    'tenant_id' =>
                        $receipt->tenant_id,

                    'business_id' =>
                        $receipt->business_id,

                    'goods_receipt_id' =>
                        $receipt->id,

                    'purchase_order_item_id' =>
                        $poItem->id,

                    'material_id' =>
                        $materialId,

                    'quantity_received' =>
                        $data[
                            'quantity_received'
                        ],

                    'sort_order' =>
                        $index,
                ]);
        }
    }

    private function remainingItems(
        PurchaseOrder $purchaseOrder
    ): array {
        $items = [];

        $poItems =
            PurchaseOrderItem::query()
                ->where(
                    'tenant_id',
                    $purchaseOrder->tenant_id
                )
                ->where(
                    'business_id',
                    $purchaseOrder->business_id
                )
                ->where(
                    'purchase_order_id',
                    $purchaseOrder->id
                )
                ->orderBy('sort_order')
                ->get();

        foreach ($poItems as $poItem) {
            $remaining =
                (float) $poItem->quantity
                - $this->postedQuantity(
                    $poItem->id
                );

            if (
                $remaining
                <= self::EPSILON
            ) {
                continue;
            }

            $items[] = [
                'purchase_order_item_id' =>
                    $poItem->id,

                'material_id' =>
                    null,

                'quantity_received' =>
                    number_format(
                        $remaining,
                        4,
                        '.',
                        ''
                    ),
            ];
        }

        return $items;
    }

    private function postedQuantity(
        string $purchaseOrderItemId
    ): float {
        return (float)
            DB::table(
                'goods_receipt_items as gri'
            )
                ->join(
                    'goods_receipts as gr',
                    'gr.id',
                    '=',
                    'gri.goods_receipt_id'
                )
                ->where(
                    'gri.tenant_id',
                    $this
                        ->tenantContext
                        ->tenantId()
                )
                ->where(
                    'gri.business_id',
                    $this
                        ->businessContext
                        ->businessId()
                )
                ->where(
                    'gri.purchase_order_item_id',
                    $purchaseOrderItemId
                )
                ->where(
                    'gr.status',
                    'POSTED'
                )
                ->sum(
                    'gri.quantity_received'
                );
    }

    private function recalculatePurchaseOrder(
        PurchaseOrder $purchaseOrder,
        string $action,
        ?string $reason = null
    ): void {
        $poItems =
            PurchaseOrderItem::query()
                ->where(
                    'tenant_id',
                    $purchaseOrder->tenant_id
                )
                ->where(
                    'business_id',
                    $purchaseOrder->business_id
                )
                ->where(
                    'purchase_order_id',
                    $purchaseOrder->id
                )
                ->get();

        $anyReceived = false;
        $allReceived =
            $poItems->isNotEmpty();

        foreach ($poItems as $poItem) {
            $received =
                $this->postedQuantity(
                    $poItem->id
                );

            if (
                $received
                > self::EPSILON
            ) {
                $anyReceived = true;
            }

            if (
                $received
                + self::EPSILON
                < (float) $poItem->quantity
            ) {
                $allReceived = false;
            }
        }

        $target =
            $allReceived
                ? 'RECEIVED'
                : (
                    $anyReceived
                        ? 'PARTIALLY_RECEIVED'
                        : 'ISSUED'
                );

        $from =
            $purchaseOrder->status;

        $purchaseOrder->status =
            $target;

        $purchaseOrder->save();

        DB::table(
            'purchase_order_status_history'
        )->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $purchaseOrder->tenant_id,

            'business_id' =>
                $purchaseOrder->business_id,

            'purchase_order_id' =>
                $purchaseOrder->id,

            'from_status' =>
                $from,

            'to_status' =>
                $target,

            'action' =>
                $action,

            'actor_user_id' =>
                $this
                    ->tenantContext
                    ->userId(),

            'reason' =>
                $reason,

            'created_at' =>
                now(),
        ]);
    }

    private function appendReceiptHistory(
        GoodsReceipt $receipt,
        ?string $from,
        string $to,
        string $action,
        ?string $reason = null
    ): void {
        GoodsReceiptStatusHistory::query()
            ->create([
                'id' =>
                    (string) Str::ulid(),

                'tenant_id' =>
                    $receipt->tenant_id,

                'business_id' =>
                    $receipt->business_id,

                'goods_receipt_id' =>
                    $receipt->id,

                'from_status' =>
                    $from,

                'to_status' =>
                    $to,

                'action' =>
                    $action,

                'actor_user_id' =>
                    $this
                        ->tenantContext
                        ->userId(),

                'reason' =>
                    $reason,

                'created_at' =>
                    now(),
            ]);
    }

    private function lockedReceipt(
        string $goodsReceiptId
    ): GoodsReceipt {
        return GoodsReceipt::query()
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
                $goodsReceiptId
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockedPurchaseOrder(
        string $purchaseOrderId
    ): PurchaseOrder {
        return PurchaseOrder::query()
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
                $purchaseOrderId
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function activeWarehouse(
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
            ->where(
                'status',
                'ACTIVE'
            )
            ->firstOrFail();
    }

    private function activeMaterial(
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
            ->where(
                'status',
                'ACTIVE'
            )
            ->firstOrFail();
    }
}
