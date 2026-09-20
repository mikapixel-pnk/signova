<?php

namespace App\Services\Purchasing;

use App\Exceptions\Purchasing\PurchaseOrderStateConflictException;
use App\Models\CatalogItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Services\Document\DocumentNumberService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly DocumentNumberService $documentNumberService,
    ) {
    }

    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?string $supplierId = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->baseQuery()
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
                                    'order_number',
                                    'ILIKE',
                                    '%' . $search . '%'
                                )
                                ->orWhere(
                                    'notes',
                                    'ILIKE',
                                    '%' . $search . '%'
                                )
                                ->orWhereHas(
                                    'supplier',
                                    fn (Builder $supplier) =>
                                        $supplier
                                            ->where(
                                                'name',
                                                'ILIKE',
                                                '%' . $search . '%'
                                            )
                                            ->orWhere(
                                                'code',
                                                'ILIKE',
                                                '%' . $search . '%'
                                            )
                                );
                        }
                    );
                }
            )
            ->when(
                $status,
                fn (
                    Builder $query,
                    string $status
                ) =>
                    $query->where(
                        'status',
                        $status
                    )
            )
            ->when(
                $supplierId,
                fn (
                    Builder $query,
                    string $supplierId
                ) =>
                    $query->where(
                        'supplier_id',
                        $supplierId
                    )
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $purchaseOrderId
    ): PurchaseOrder {
        return $this->baseQuery()
            ->where(
                'id',
                $purchaseOrderId
            )
            ->firstOrFail();
    }

    public function createDraft(
        array $data
    ): PurchaseOrder {
        return DB::transaction(
            function () use (
                $data
            ): PurchaseOrder {
                $tenantId =
                    $this->tenantContext
                        ->tenantId();

                $businessId =
                    $this->businessContext
                        ->businessId();

                $userId =
                    $this->tenantContext
                        ->userId();

                $supplier =
                    $this->activeSupplier(
                        $data['supplier_id']
                    );

                $sourceRequest =
                    isset(
                        $data[
                            'source_purchase_request_id'
                        ]
                    )
                        ? $this->approvedSourceRequest(
                            $data[
                                'source_purchase_request_id'
                            ]
                        )
                        : null;

                $currency =
                    strtoupper(
                        $data['currency']
                        ?? $sourceRequest?->currency
                        ?? 'IDR'
                    );

                if (
                    $sourceRequest
                    && $currency
                        !== $sourceRequest->currency
                ) {
                    throw ValidationException::withMessages([
                        'currency' =>
                            'Mata uang Pesanan Pembelian harus sama dengan Permintaan Pembelian sumber.',
                    ]);
                }

                $purchaseOrder =
                    PurchaseOrder::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $tenantId,

                            'business_id' =>
                                $businessId,

                            'order_number' =>
                                $this
                                    ->documentNumberService
                                    ->nextPurchaseOrderNumber(),

                            'supplier_id' =>
                                $supplier->id,

                            'source_purchase_request_id' =>
                                $sourceRequest?->id,

                            'status' =>
                                'DRAFT',

                            'currency' =>
                                $currency,

                            'expected_at' =>
                                $data['expected_at']
                                ?? null,

                            'subtotal' =>
                                '0.00',

                            'discount_total' =>
                                '0.00',

                            'tax_total' =>
                                '0.00',

                            'total' =>
                                '0.00',

                            'notes' =>
                                $data['notes']
                                ?? null,

                            'created_by_user_id' =>
                                $userId,
                        ]);

                $items =
                    $data['items']
                    ?? (
                        $sourceRequest
                            ? $this->remainingSourceItems(
                                $sourceRequest,
                                $purchaseOrder->id
                            )
                            : []
                    );

                if ($items === []) {
                    throw ValidationException::withMessages([
                        'items' =>
                            'Pesanan Pembelian harus memiliki minimal satu item.',
                    ]);
                }

                $totals =
                    $this->replaceItems(
                        $purchaseOrder,
                        $items
                    );

                $purchaseOrder->fill(
                    $totals
                );

                $purchaseOrder->save();

                $this->appendHistory(
                    $purchaseOrder,
                    null,
                    'DRAFT',
                    'CREATED'
                );

                return $this->findOrFail(
                    $purchaseOrder->id
                );
            }
        );
    }

    public function updateDraft(
        string $purchaseOrderId,
        array $data
    ): PurchaseOrder {
        return DB::transaction(
            function () use (
                $purchaseOrderId,
                $data
            ): PurchaseOrder {
                $purchaseOrder =
                    $this->locked(
                        $purchaseOrderId
                    );

                if (
                    $purchaseOrder->status
                    !== 'DRAFT'
                ) {
                    throw new PurchaseOrderStateConflictException(
                        'Pesanan Pembelian hanya dapat diubah saat masih Draf.'
                    );
                }

                if (
                    array_key_exists(
                        'supplier_id',
                        $data
                    )
                ) {
                    $purchaseOrder->supplier_id =
                        $this->activeSupplier(
                            $data['supplier_id']
                        )->id;
                }

                foreach (
                    [
                        'expected_at',
                        'notes',
                    ] as $field
                ) {
                    if (
                        array_key_exists(
                            $field,
                            $data
                        )
                    ) {
                        $purchaseOrder->{$field} =
                            $data[$field];
                    }
                }

                if (
                    array_key_exists(
                        'currency',
                        $data
                    )
                ) {
                    $currency =
                        strtoupper(
                            $data['currency']
                        );

                    if (
                        $purchaseOrder
                            ->source_purchase_request_id
                    ) {
                        $sourceRequest =
                            $this->approvedSourceRequest(
                                $purchaseOrder
                                    ->source_purchase_request_id
                            );

                        if (
                            $currency
                            !== $sourceRequest->currency
                        ) {
                            throw ValidationException::withMessages([
                                'currency' =>
                                    'Mata uang Pesanan Pembelian harus sama dengan Permintaan Pembelian sumber.',
                            ]);
                        }
                    }

                    $purchaseOrder->currency =
                        $currency;
                }

                if (
                    array_key_exists(
                        'items',
                        $data
                    )
                ) {
                    $purchaseOrder->fill(
                        $this->replaceItems(
                            $purchaseOrder,
                            $data['items']
                        )
                    );
                }

                $purchaseOrder->save();

                return $this->findOrFail(
                    $purchaseOrder->id
                );
            }
        );
    }

    public function issue(
        string $purchaseOrderId
    ): PurchaseOrder {
        return DB::transaction(
            function () use (
                $purchaseOrderId
            ): PurchaseOrder {
                $purchaseOrder =
                    $this->locked(
                        $purchaseOrderId
                    );

                if (
                    $purchaseOrder->status
                    !== 'DRAFT'
                ) {
                    throw new PurchaseOrderStateConflictException(
                        'Hanya Pesanan Pembelian berstatus Draf yang dapat diterbitkan.'
                    );
                }

                if (
                    ! PurchaseOrderItem::query()
                        ->where(
                            'purchase_order_id',
                            $purchaseOrder->id
                        )
                        ->exists()
                ) {
                    throw new PurchaseOrderStateConflictException(
                        'Pesanan Pembelian harus memiliki minimal satu item.'
                    );
                }

                $from =
                    $purchaseOrder->status;

                $purchaseOrder->status =
                    'ISSUED';

                $purchaseOrder
                    ->issued_by_user_id =
                    $this->tenantContext
                        ->userId();

                $purchaseOrder->issued_at =
                    now();

                $purchaseOrder->save();

                $this->appendHistory(
                    $purchaseOrder,
                    $from,
                    'ISSUED',
                    'ISSUED'
                );

                return $this->findOrFail(
                    $purchaseOrder->id
                );
            }
        );
    }

    public function cancel(
        string $purchaseOrderId,
        string $reason
    ): PurchaseOrder {
        return DB::transaction(
            function () use (
                $purchaseOrderId,
                $reason
            ): PurchaseOrder {
                $purchaseOrder =
                    $this->locked(
                        $purchaseOrderId
                    );

                if (
                    ! in_array(
                        $purchaseOrder->status,
                        [
                            'DRAFT',
                            'ISSUED',
                        ],
                        true
                    )
                ) {
                    throw new PurchaseOrderStateConflictException(
                        'Pesanan Pembelian pada status ini tidak dapat dibatalkan.'
                    );
                }

                $from =
                    $purchaseOrder->status;

                $purchaseOrder->status =
                    'CANCELLED';

                $purchaseOrder
                    ->cancelled_by_user_id =
                    $this->tenantContext
                        ->userId();

                $purchaseOrder->cancelled_at =
                    now();

                $purchaseOrder
                    ->cancellation_reason =
                    trim($reason);

                $purchaseOrder->save();

                $this->appendHistory(
                    $purchaseOrder,
                    $from,
                    'CANCELLED',
                    'CANCELLED',
                    trim($reason)
                );

                return $this->findOrFail(
                    $purchaseOrder->id
                );
            }
        );
    }

    private function replaceItems(
        PurchaseOrder $purchaseOrder,
        array $items
    ): array {
        PurchaseOrderItem::query()
            ->where(
                'purchase_order_id',
                $purchaseOrder->id
            )
            ->delete();

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $total = 0.0;

        foreach (
            array_values($items)
            as $index => $item
        ) {
            $snapshot =
                $this->resolveItemSnapshot(
                    $purchaseOrder,
                    $item,
                    $index
                );

            PurchaseOrderItem::query()
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

                    ...$snapshot,
                ]);

            $subtotal +=
                (float) $snapshot[
                    '_gross'
                ];

            $discountTotal +=
                (float) $snapshot[
                    'discount_amount'
                ];

            $taxTotal +=
                (float) $snapshot[
                    'tax_amount'
                ];

            $total +=
                (float) $snapshot[
                    'amount'
                ];
        }

        return [
            'subtotal' =>
                $this->money(
                    $subtotal
                ),

            'discount_total' =>
                $this->money(
                    $discountTotal
                ),

            'tax_total' =>
                $this->money(
                    $taxTotal
                ),

            'total' =>
                $this->money(
                    $total
                ),
        ];
    }

    private function resolveItemSnapshot(
        PurchaseOrder $purchaseOrder,
        array $item,
        int $index
    ): array {
        $tenantId =
            $this->tenantContext
                ->tenantId();

        $businessId =
            $this->businessContext
                ->businessId();

        $sourceItem = null;

        if (
            ! empty(
                $item[
                    'source_purchase_request_item_id'
                ]
            )
        ) {
            if (
                ! $purchaseOrder
                    ->source_purchase_request_id
            ) {
                throw ValidationException::withMessages([
                    "items.{$index}.source_purchase_request_item_id" =>
                        'Item sumber hanya dapat digunakan pada PO yang terhubung ke Permintaan Pembelian.',
                ]);
            }

            $sourceItem =
                PurchaseRequestItem::query()
                    ->where(
                        'tenant_id',
                        $tenantId
                    )
                    ->where(
                        'business_id',
                        $businessId
                    )
                    ->where(
                        'purchase_request_id',
                        $purchaseOrder
                            ->source_purchase_request_id
                    )
                    ->where(
                        'id',
                        $item[
                            'source_purchase_request_item_id'
                        ]
                    )
                    ->firstOrFail();
        } elseif (
            $purchaseOrder
                ->source_purchase_request_id
        ) {
            throw ValidationException::withMessages([
                "items.{$index}.source_purchase_request_item_id" =>
                    'Item PO yang berasal dari Permintaan Pembelian wajib menyertakan item sumber.',
            ]);
        }

        $catalogItem = null;

        $catalogItemId =
            $item['catalog_item_id']
            ?? $sourceItem?->catalog_item_id;

        if (
            $catalogItemId
            && ! $sourceItem
        ) {
            $catalogItem =
                CatalogItem::query()
                    ->where(
                        'tenant_id',
                        $tenantId
                    )
                    ->where(
                        'business_id',
                        $businessId
                    )
                    ->where(
                        'id',
                        $catalogItemId
                    )
                    ->where(
                        'status',
                        'ACTIVE'
                    )
                    ->firstOrFail();
        }

        $unitId =
            $item['unit_id']
            ?? $sourceItem?->unit_id
            ?? $catalogItem?->unit_id;

        $unit = null;

        if (
            $unitId
            && ! $sourceItem
        ) {
            $unit =
                DB::table('units')
                    ->where(
                        'tenant_id',
                        $tenantId
                    )
                    ->where(
                        'business_id',
                        $businessId
                    )
                    ->where(
                        'id',
                        $unitId
                    )
                    ->first();

            if ($unit === null) {
                throw (
                    new ModelNotFoundException()
                )->setModel(
                    'Unit',
                    [$unitId]
                );
            }
        }

        $quantity =
            (float) (
                $item['quantity']
                ?? '1'
            );

        if ($sourceItem) {
            $this->assertSourceQuantityAvailable(
                $sourceItem,
                $purchaseOrder->id,
                $quantity,
                $index
            );
        }

        $unitPrice =
            (float) (
                $item['unit_price']
                ?? $sourceItem
                    ?->estimated_unit_price
                ?? '0'
            );

        $discount =
            (float) (
                $item['discount_amount']
                ?? '0'
            );

        $tax =
            (float) (
                $item['tax_amount']
                ?? '0'
            );

        $gross =
            round(
                $quantity
                * $unitPrice,
                2
            );

        if ($discount > $gross) {
            throw ValidationException::withMessages([
                "items.{$index}.discount_amount" =>
                    'Diskon item tidak boleh melebihi nilai sebelum diskon.',
            ]);
        }

        $amount =
            round(
                $gross
                - $discount
                + $tax,
                2
            );

        return [
            'source_purchase_request_item_id' =>
                $sourceItem?->id,

            'catalog_item_id' =>
                $sourceItem?->catalog_item_id
                ?? $catalogItem?->id,

            'unit_id' =>
                $unitId,

            'item_type' =>
                $sourceItem?->item_type
                ?? $catalogItem?->type
                ?? (
                    $item['item_type']
                    ?? 'PRODUCT'
                ),

            'code' =>
                $sourceItem?->code
                ?? $catalogItem?->code
                ?? (
                    $item['code']
                    ?? null
                ),

            'name' =>
                $sourceItem?->name
                ?? $catalogItem?->name
                ?? trim(
                    $item['name']
                ),

            'description' =>
                $item['description']
                ?? $sourceItem?->description
                ?? $catalogItem?->description,

            'quantity' =>
                number_format(
                    $quantity,
                    4,
                    '.',
                    ''
                ),

            'unit_code' =>
                $sourceItem?->unit_code
                ?? data_get(
                    $unit,
                    'code'
                ),

            'unit_name' =>
                $sourceItem?->unit_name
                ?? data_get(
                    $unit,
                    'name'
                ),

            'unit_symbol' =>
                $sourceItem?->unit_symbol
                ?? data_get(
                    $unit,
                    'symbol'
                ),

            'unit_price' =>
                $this->money(
                    $unitPrice
                ),

            'discount_amount' =>
                $this->money(
                    $discount
                ),

            'tax_amount' =>
                $this->money(
                    $tax
                ),

            'amount' =>
                $this->money(
                    $amount
                ),

            'sort_order' =>
                $item['sort_order']
                ?? $index,

            '_gross' =>
                $this->money(
                    $gross
                ),
        ];
    }

    private function remainingSourceItems(
        PurchaseRequest $sourceRequest,
        string $purchaseOrderId
    ): array {
        $items = [];

        foreach (
            $sourceRequest->items
            as $sourceItem
        ) {
            $allocated =
                $this->allocatedSourceQuantity(
                    $sourceItem->id,
                    $purchaseOrderId
                );

            $remaining =
                (float) $sourceItem->quantity
                - $allocated;

            if ($remaining <= 0) {
                continue;
            }

            $items[] = [
                'source_purchase_request_item_id' =>
                    $sourceItem->id,

                'quantity' =>
                    $remaining,

                'unit_price' =>
                    $sourceItem
                        ->estimated_unit_price,

                'discount_amount' =>
                    '0',

                'tax_amount' =>
                    '0',

                'sort_order' =>
                    $sourceItem->sort_order,
            ];
        }

        if ($items === []) {
            throw new PurchaseOrderStateConflictException(
                'Seluruh kuantitas Permintaan Pembelian sudah dialokasikan ke PO aktif.'
            );
        }

        return $items;
    }

    private function assertSourceQuantityAvailable(
        PurchaseRequestItem $sourceItem,
        string $purchaseOrderId,
        float $quantity,
        int $index
    ): void {
        $allocated =
            $this->allocatedSourceQuantity(
                $sourceItem->id,
                $purchaseOrderId
            );

        $available =
            (float) $sourceItem->quantity
            - $allocated;

        if (
            $quantity
            > $available + 0.0000001
        ) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity" =>
                    'Kuantitas PO melebihi sisa kuantitas pada Permintaan Pembelian.',
            ]);
        }
    }

    private function allocatedSourceQuantity(
        string $sourceItemId,
        string $excludePurchaseOrderId
    ): float {
        return (float) (
            DB::table(
                'purchase_order_items as poi'
            )
                ->join(
                    'purchase_orders as po',
                    'po.id',
                    '=',
                    'poi.purchase_order_id'
                )
                ->where(
                    'poi.tenant_id',
                    $this->tenantContext
                        ->tenantId()
                )
                ->where(
                    'poi.business_id',
                    $this->businessContext
                        ->businessId()
                )
                ->where(
                    'poi.source_purchase_request_item_id',
                    $sourceItemId
                )
                ->where(
                    'po.status',
                    '!=',
                    'CANCELLED'
                )
                ->where(
                    'po.id',
                    '!=',
                    $excludePurchaseOrderId
                )
                ->sum(
                    'poi.quantity'
                )
        );
    }

    private function activeSupplier(
        string $supplierId
    ): Supplier {
        return Supplier::query()
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
                $supplierId
            )
            ->where(
                'status',
                'ACTIVE'
            )
            ->firstOrFail();
    }

    private function approvedSourceRequest(
        string $purchaseRequestId
    ): PurchaseRequest {
        $purchaseRequest =
            PurchaseRequest::query()
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
                    $purchaseRequestId
                )
                ->with([
                    'items' =>
                        fn ($query) =>
                            $query->orderBy(
                                'sort_order'
                            ),
                ])
                ->firstOrFail();

        if (
            $purchaseRequest->status
            !== 'APPROVED'
        ) {
            throw new PurchaseOrderStateConflictException(
                'PO hanya dapat dibuat dari Permintaan Pembelian yang telah Disetujui.'
            );
        }

        return $purchaseRequest;
    }

    private function appendHistory(
        PurchaseOrder $purchaseOrder,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?string $reason = null
    ): void {
        PurchaseOrderStatusHistory::query()
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

                'from_status' =>
                    $fromStatus,

                'to_status' =>
                    $toStatus,

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

    private function locked(
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

    private function baseQuery(): Builder
    {
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
            ->with([
                'supplier',
                'sourcePurchaseRequest',
                'items' =>
                    fn ($query) =>
                        $query->orderBy(
                            'sort_order'
                        ),
            ]);
    }

    private function money(
        float $value
    ): string {
        return number_format(
            round(
                $value,
                2
            ),
            2,
            '.',
            ''
        );
    }
}
