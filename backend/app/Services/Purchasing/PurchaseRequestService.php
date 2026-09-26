<?php

namespace App\Services\Purchasing;

use App\Exceptions\Purchasing\PurchaseRequestStateConflictException;
use App\Models\CatalogItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseRequestStatusHistory;
use App\Services\Document\DocumentNumberService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseRequestService
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
                                    'request_number',
                                    'ILIKE',
                                    '%' . $search . '%'
                                )
                                ->orWhere(
                                    'notes',
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
                    string $status
                ) =>
                    $query->where(
                        'status',
                        $status
                    )
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $purchaseRequestId
    ): PurchaseRequest {
        return $this->baseQuery()
            ->where(
                'id',
                $purchaseRequestId
            )
            ->firstOrFail();
    }

    public function createDraft(
        array $data
    ): PurchaseRequest {
        return DB::transaction(
            function () use (
                $data
            ): PurchaseRequest {
                $tenantId =
                    $this->tenantContext
                        ->tenantId();

                $businessId =
                    $this->businessContext
                        ->businessId();

                $userId =
                    $this->tenantContext
                        ->userId();

                $purchaseRequest =
                    PurchaseRequest::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $tenantId,

                            'business_id' =>
                                $businessId,

                            'request_number' =>
                                $this
                                    ->documentNumberService
                                    ->nextPurchaseRequestNumber(),

                            'status' =>
                                'DRAFT',

                            'needed_at' =>
                                $data['needed_at']
                                ?? null,

                            'currency' =>
                                strtoupper(
                                    $data['currency']
                                    ?? 'IDR'
                                ),

                            'estimated_total' =>
                                '0.00',

                            'notes' =>
                                $data['notes']
                                ?? null,

                            'requested_by_user_id' =>
                                $userId,
                        ]);

                $purchaseRequest
                    ->estimated_total =
                    $this->replaceItems(
                        $purchaseRequest,
                        $data['items']
                    );

                $purchaseRequest->save();

                $this->appendHistory(
                    $purchaseRequest,
                    null,
                    'DRAFT',
                    'CREATED'
                );

                return $this->findOrFail(
                    $purchaseRequest->id
                );
            }
        );
    }

    public function updateDraft(
        string $purchaseRequestId,
        array $data
    ): PurchaseRequest {
        return DB::transaction(
            function () use (
                $purchaseRequestId,
                $data
            ): PurchaseRequest {
                $purchaseRequest =
                    $this->locked(
                        $purchaseRequestId
                    );

                if (
                    $purchaseRequest->status
                    !== 'DRAFT'
                ) {
                    throw new PurchaseRequestStateConflictException(
                        'Permintaan pembelian hanya dapat diubah saat masih Draf.'
                    );
                }

                foreach (
                    [
                        'needed_at',
                        'currency',
                        'notes',
                    ] as $field
                ) {
                    if (
                        array_key_exists(
                            $field,
                            $data
                        )
                    ) {
                        $purchaseRequest->{$field} =
                            $field === 'currency'
                                ? strtoupper(
                                    $data[$field]
                                )
                                : $data[$field];
                    }
                }

                if (
                    array_key_exists(
                        'items',
                        $data
                    )
                ) {
                    $purchaseRequest
                        ->estimated_total =
                        $this->replaceItems(
                            $purchaseRequest,
                            $data['items']
                        );
                }

                $purchaseRequest->save();

                return $this->findOrFail(
                    $purchaseRequest->id
                );
            }
        );
    }

    public function submit(
        string $purchaseRequestId
    ): PurchaseRequest {
        return DB::transaction(
            function () use (
                $purchaseRequestId
            ): PurchaseRequest {
                $purchaseRequest =
                    $this->locked(
                        $purchaseRequestId
                    );

                if (
                    $purchaseRequest->status
                    !== 'DRAFT'
                ) {
                    throw new PurchaseRequestStateConflictException(
                        'Hanya Permintaan Pembelian berstatus Draf yang dapat diajukan.'
                    );
                }

                if (
                    ! PurchaseRequestItem::query()
                        ->where(
                            'purchase_request_id',
                            $purchaseRequest->id
                        )
                        ->exists()
                ) {
                    throw new PurchaseRequestStateConflictException(
                        'Permintaan Pembelian harus memiliki minimal satu item.'
                    );
                }

                $from =
                    $purchaseRequest->status;

                $purchaseRequest->status =
                    'SUBMITTED';

                $purchaseRequest
                    ->submitted_by_user_id =
                    $this->tenantContext
                        ->userId();

                $purchaseRequest->submitted_at =
                    now();

                $purchaseRequest->save();

                $this->appendHistory(
                    $purchaseRequest,
                    $from,
                    'SUBMITTED',
                    'SUBMITTED'
                );

                return $this->findOrFail(
                    $purchaseRequest->id
                );
            }
        );
    }

    public function approve(
        string $purchaseRequestId
    ): PurchaseRequest {
        return DB::transaction(
            function () use (
                $purchaseRequestId
            ): PurchaseRequest {
                $purchaseRequest =
                    $this->locked(
                        $purchaseRequestId
                    );

                if (
                    $purchaseRequest->status
                    !== 'SUBMITTED'
                ) {
                    throw new PurchaseRequestStateConflictException(
                        'Hanya Permintaan Pembelian yang telah diajukan yang dapat disetujui.'
                    );
                }

                $from =
                    $purchaseRequest->status;

                $purchaseRequest->status =
                    'APPROVED';

                $purchaseRequest
                    ->approved_by_user_id =
                    $this->tenantContext
                        ->userId();

                $purchaseRequest->approved_at =
                    now();

                $purchaseRequest->save();

                $this->appendHistory(
                    $purchaseRequest,
                    $from,
                    'APPROVED',
                    'APPROVED'
                );

                return $this->findOrFail(
                    $purchaseRequest->id
                );
            }
        );
    }

    public function reject(
        string $purchaseRequestId,
        string $reason
    ): PurchaseRequest {
        return DB::transaction(
            function () use (
                $purchaseRequestId,
                $reason
            ): PurchaseRequest {
                $purchaseRequest =
                    $this->locked(
                        $purchaseRequestId
                    );

                if (
                    $purchaseRequest->status
                    !== 'SUBMITTED'
                ) {
                    throw new PurchaseRequestStateConflictException(
                        'Hanya Permintaan Pembelian yang telah diajukan yang dapat ditolak.'
                    );
                }

                $from =
                    $purchaseRequest->status;

                $purchaseRequest->status =
                    'REJECTED';

                $purchaseRequest
                    ->rejected_by_user_id =
                    $this->tenantContext
                        ->userId();

                $purchaseRequest->rejected_at =
                    now();

                $purchaseRequest
                    ->rejection_reason =
                    trim($reason);

                $purchaseRequest->save();

                $this->appendHistory(
                    $purchaseRequest,
                    $from,
                    'REJECTED',
                    'REJECTED',
                    trim($reason)
                );

                return $this->findOrFail(
                    $purchaseRequest->id
                );
            }
        );
    }

    public function revise(
        string $purchaseRequestId
    ): PurchaseRequest {
        return DB::transaction(
            function () use (
                $purchaseRequestId
            ): PurchaseRequest {
                $purchaseRequest =
                    $this->locked(
                        $purchaseRequestId
                    );

                if (
                    $purchaseRequest->status
                    !== 'REJECTED'
                ) {
                    throw new PurchaseRequestStateConflictException(
                        'Hanya Permintaan Pembelian yang Ditolak yang dapat diperbaiki.'
                    );
                }

                $from =
                    $purchaseRequest->status;

                $purchaseRequest->status =
                    'DRAFT';

                $purchaseRequest
                    ->submitted_by_user_id =
                    null;

                $purchaseRequest->submitted_at =
                    null;

                $purchaseRequest
                    ->rejected_by_user_id =
                    null;

                $purchaseRequest->rejected_at =
                    null;

                $purchaseRequest
                    ->rejection_reason =
                    null;

                $purchaseRequest->save();

                $this->appendHistory(
                    $purchaseRequest,
                    $from,
                    'DRAFT',
                    'REVISED'
                );

                return $this->findOrFail(
                    $purchaseRequest->id
                );
            }
        );
    }

    public function cancel(
        string $purchaseRequestId,
        string $reason
    ): PurchaseRequest {
        return DB::transaction(
            function () use (
                $purchaseRequestId,
                $reason
            ): PurchaseRequest {
                $purchaseRequest =
                    $this->locked(
                        $purchaseRequestId
                    );

                if (
                    ! in_array(
                        $purchaseRequest->status,
                        [
                            'DRAFT',
                            'SUBMITTED',
                            'REJECTED',
                        ],
                        true
                    )
                ) {
                    throw new PurchaseRequestStateConflictException(
                        'Permintaan Pembelian pada status ini tidak dapat dibatalkan.'
                    );
                }

                $from =
                    $purchaseRequest->status;

                $purchaseRequest->status =
                    'CANCELLED';

                $purchaseRequest
                    ->cancelled_by_user_id =
                    $this->tenantContext
                        ->userId();

                $purchaseRequest->cancelled_at =
                    now();

                $purchaseRequest
                    ->cancellation_reason =
                    trim($reason);

                $purchaseRequest->save();

                $this->appendHistory(
                    $purchaseRequest,
                    $from,
                    'CANCELLED',
                    'CANCELLED',
                    trim($reason)
                );

                return $this->findOrFail(
                    $purchaseRequest->id
                );
            }
        );
    }

    private function replaceItems(
        PurchaseRequest $purchaseRequest,
        array $items
    ): string {
        PurchaseRequestItem::query()
            ->where(
                'purchase_request_id',
                $purchaseRequest->id
            )
            ->delete();

        $total =
            BigDecimal::zero();

        foreach (
            array_values($items)
            as $index => $item
        ) {
            $snapshot =
                $this->resolveItemSnapshot(
                    $item,
                    $index
                );

            PurchaseRequestItem::query()
                ->create([
                    'id' =>
                        (string) Str::ulid(),

                    'tenant_id' =>
                        $purchaseRequest
                            ->tenant_id,

                    'business_id' =>
                        $purchaseRequest
                            ->business_id,

                    'purchase_request_id' =>
                        $purchaseRequest->id,

                    ...$snapshot,
                ]);

            $total =
                $total->plus(
                    (string)
                        $snapshot['amount']
                );
        }

        return $this->money(
            $total
        );
    }

    private function resolveItemSnapshot(
        array $item,
        int $index
    ): array {
        $tenantId =
            $this->tenantContext
                ->tenantId();

        $businessId =
            $this->businessContext
                ->businessId();

        $catalogItem = null;

        if (
            ! empty(
                $item['catalog_item_id']
            )
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
                        $item[
                            'catalog_item_id'
                        ]
                    )
                    ->where(
                        'status',
                        'ACTIVE'
                    )
                    ->firstOrFail();
        }

        $material = null;

        if (
            ! empty(
                $item['material_id']
            )
        ) {
            $material =
                \App\Models\Material::query()
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
                        $item['material_id']
                    )
                    ->where(
                        'status',
                        'ACTIVE'
                    )
                    ->firstOrFail();
        }

        $unitId =
            $item['unit_id']
            ?? $material?->unit_id
            ?? $catalogItem?->unit_id;

        $unit = null;

        if ($unitId) {
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

        $legacyItemType =
            strtoupper(
                (string) (
                    $catalogItem?->type
                    ?? $item['item_type']
                    ?? 'PRODUCT'
                )
            );

        $procurementType =
            strtoupper(
                (string) (
                    $item['procurement_type']
                    ?? ''
                )
            );

        if ($procurementType === '') {
            if ($material) {
                $stockTracking =
                    strtoupper(
                        (string) (
                            $material
                                ->stock_tracking
                            ?: 'TRACKED'
                        )
                    );

                $procurementType =
                    $stockTracking
                        === 'NOT_TRACKED'
                        ? 'NON_STOCK_GOOD'
                        : 'INVENTORY_ITEM';
            } else {
                $procurementType =
                    $legacyItemType
                        === 'SERVICE'
                        ? 'SERVICE'
                        : 'NON_STOCK_GOOD';
            }
        }

        if (
            $procurementType
                === 'INVENTORY_ITEM'
            && ! $material
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "items.{$index}.material_id" =>
                    'Item persediaan wajib memilih Bahan & Persediaan.',
            ]);
        }

        if (
            $procurementType
                === 'SERVICE'
            && $material
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "items.{$index}.material_id" =>
                    'Jasa tidak boleh menggunakan Bahan & Persediaan.',
            ]);
        }

        if (
            $material
            && $procurementType
                !== 'SERVICE'
        ) {
            $stockTracking =
                strtoupper(
                    (string) (
                        $material
                            ->stock_tracking
                        ?: 'TRACKED'
                    )
                );

            $expectedProcurementType =
                $stockTracking
                    === 'NOT_TRACKED'
                    ? 'NON_STOCK_GOOD'
                    : 'INVENTORY_ITEM';

            if (
                $procurementType
                !== $expectedProcurementType
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "items.{$index}.material_id" =>
                        $stockTracking
                            === 'NOT_TRACKED'
                            ? 'Barang yang stoknya tidak dilacak harus menggunakan jenis Barang Non-Stok.'
                            : 'Barang yang stoknya dilacak harus menggunakan jenis Item Persediaan.',
                ]);
            }
        }

        if (
            $material
            && $material->unit_id
            && $unitId
            && $material->unit_id
                !== $unitId
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "items.{$index}.unit_id" =>
                    'Satuan item harus sesuai dengan satuan Bahan & Persediaan.',
            ]);
        }

        $quantity =
            BigDecimal::of(
                (string) (
                    $item['quantity']
                    ?? '1'
                )
            )
                ->toScale(
                    4,
                    RoundingMode::HalfUp
                )
                ->__toString();

        $unitPrice =
            BigDecimal::of(
                (string) (
                    $item[
                        'estimated_unit_price'
                    ]
                    ?? '0'
                )
            )
                ->toScale(
                    2,
                    RoundingMode::HalfUp
                )
                ->__toString();

        $amount =
            $this->money(
                BigDecimal::of(
                    $quantity
                )->multipliedBy(
                    $unitPrice
                )
            );

        return [
            'catalog_item_id' =>
                $catalogItem?->id,

            'material_id' =>
                $material?->id,

            'unit_id' =>
                $unitId,

            'procurement_type' =>
                $procurementType,

            'item_type' =>
                $procurementType
                    === 'SERVICE'
                    ? 'SERVICE'
                    : 'PRODUCT',

            'code' =>
                $material?->code
                ?? $catalogItem?->code
                ?? (
                    $item['code']
                    ?? null
                ),

            'name' =>
                $material?->name
                ?? $catalogItem?->name
                ?? trim(
                    $item['name']
                ),

            'description' =>
                $item['description']
                ?? $catalogItem?->description,

            'quantity' =>
                $quantity,

            'unit_code' =>
                data_get(
                    $unit,
                    'code'
                ),

            'unit_name' =>
                data_get(
                    $unit,
                    'name'
                ),

            'unit_symbol' =>
                data_get(
                    $unit,
                    'symbol'
                ),

            'estimated_unit_price' =>
                $unitPrice,

            'amount' =>
                $amount,

            'sort_order' =>
                $item['sort_order']
                ?? $index,
        ];
    }

    private function money(
        BigDecimal $value
    ): string {
        return $value
            ->toScale(
                2,
                RoundingMode::HalfUp
            )
            ->__toString();
    }

    private function appendHistory(
        PurchaseRequest $purchaseRequest,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?string $reason = null
    ): void {
        PurchaseRequestStatusHistory::query()
            ->create([
                'id' =>
                    (string) Str::ulid(),

                'tenant_id' =>
                    $purchaseRequest
                        ->tenant_id,

                'business_id' =>
                    $purchaseRequest
                        ->business_id,

                'purchase_request_id' =>
                    $purchaseRequest->id,

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
        string $purchaseRequestId
    ): PurchaseRequest {
        return PurchaseRequest::query()
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
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function baseQuery(): Builder
    {
        return PurchaseRequest::query()
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
                'items' =>
                    fn ($query) =>
                        $query->orderBy(
                            'sort_order'
                        ),
            ]);
    }
}
