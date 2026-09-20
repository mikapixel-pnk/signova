<?php

namespace App\Services\Finance;

use App\Exceptions\Finance\SupplierBillStateConflictException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillStatusHistory;
use App\Services\Document\DocumentNumberService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierBillService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly DocumentNumberService $documentNumberService,
    ) {
    }

    public function paginate(
        ?string $search,
        ?string $status,
        ?string $supplierId,
        int $perPage = 20
    ): LengthAwarePaginator {
        $tenantId =
            $this->tenantContext
                ->tenantId();

        $businessId =
            $this->businessContext
                ->businessId();

        return $this->withPaymentAmounts(
            SupplierBill::query()
        )
            ->where(
                'tenant_id',
                $tenantId
            )
            ->where(
                'business_id',
                $businessId
            )
            ->when(
                $status,
                fn ($query) =>
                    $query->where(
                        'status',
                        $status
                    )
            )
            ->when(
                $supplierId,
                fn ($query) =>
                    $query->where(
                        'supplier_id',
                        $supplierId
                    )
            )
            ->when(
                $search,
                function ($query) use (
                    $search
                ): void {
                    $term =
                        '%' .
                        trim($search) .
                        '%';

                    $query->where(
                        function ($query) use (
                            $term
                        ): void {
                            $query
                                ->where(
                                    'bill_number',
                                    'ilike',
                                    $term
                                )
                                ->orWhere(
                                    'supplier_invoice_number',
                                    'ilike',
                                    $term
                                )
                                ->orWhereHas(
                                    'supplier',
                                    fn ($supplierQuery) =>
                                        $supplierQuery
                                            ->where(
                                                'name',
                                                'ilike',
                                                $term
                                            )
                                );
                        }
                    );
                }
            )
            ->with([
                'supplier',
                'purchaseOrder',
                'goodsReceipt',
            ])
            ->orderByDesc('bill_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $supplierBillId
    ): SupplierBill {
        return $this->withPaymentAmounts(
            $this->baseQuery()
        )
            ->where(
                'id',
                $supplierBillId
            )
            ->with([
                'supplier',
                'purchaseOrder',
                'goodsReceipt',
            ])
            ->firstOrFail();
    }

    public function create(
        array $data
    ): SupplierBill {
        try {
            return DB::transaction(
                function () use (
                    $data
                ): SupplierBill {
                    $receipt =
                        $this->lockedReceipt(
                            $data[
                                'goods_receipt_id'
                            ]
                        );

                    if (
                        $receipt->status
                        !== 'POSTED'
                    ) {
                        throw new SupplierBillStateConflictException(
                            'Tagihan Pemasok hanya dapat dibuat dari Penerimaan yang sudah Dicatat.'
                        );
                    }

                    $purchaseOrder =
                        $this->lockedPurchaseOrder(
                            $receipt
                                ->purchase_order_id
                        );

                    $supplier =
                        $this->supplier(
                            $purchaseOrder
                                ->supplier_id
                        );

                    $this->assertReceiptAvailable(
                        $receipt->id
                    );

                    $supplierInvoiceNumber =
                        $this->normalizeOptionalText(
                            $data[
                                'supplier_invoice_number'
                            ]
                            ?? null
                        );

                    $this->assertSupplierInvoiceAvailable(
                        $supplier->id,
                        $supplierInvoiceNumber
                    );

                    $totals =
                        $this->totalsFromReceipt(
                            $receipt,
                            $purchaseOrder
                        );

                    $billDate =
                        $data['bill_date']
                        ?? $this->defaultBillDate();

                    $dueDate =
                        CarbonImmutable::parse(
                            $billDate
                        )
                            ->addDays(
                                $supplier
                                    ->payment_terms_days
                            )
                            ->toDateString();

                    $bill =
                        SupplierBill::query()
                            ->create([
                                'id' =>
                                    (string) Str::ulid(),

                                'tenant_id' =>
                                    $receipt->tenant_id,

                                'business_id' =>
                                    $receipt->business_id,

                                'bill_number' =>
                                    $this
                                        ->documentNumberService
                                        ->nextSupplierBillNumber(),

                                'supplier_id' =>
                                    $supplier->id,

                                'purchase_order_id' =>
                                    $purchaseOrder->id,

                                'goods_receipt_id' =>
                                    $receipt->id,

                                'supplier_invoice_number' =>
                                    $supplierInvoiceNumber,

                                'status' =>
                                    'DRAFT',

                                'currency' =>
                                    $purchaseOrder
                                        ->currency,

                                'bill_date' =>
                                    $billDate,

                                'due_date' =>
                                    $dueDate,

                                'subtotal' =>
                                    $totals[
                                        'subtotal'
                                    ],

                                'discount_total' =>
                                    $totals[
                                        'discount_total'
                                    ],

                                'tax_total' =>
                                    $totals[
                                        'tax_total'
                                    ],

                                'total' =>
                                    $totals[
                                        'total'
                                    ],

                                'notes' =>
                                    $data['notes']
                                    ?? null,

                                'created_by_user_id' =>
                                    $this
                                        ->tenantContext
                                        ->userId(),
                            ]);

                    $this->appendHistory(
                        $bill,
                        null,
                        'DRAFT',
                        'CREATED'
                    );

                    return $this->findOrFail(
                        $bill->id
                    );
                },
                3
            );
        } catch (QueryException $exception) {
            $this->translateUniqueConflict(
                $exception
            );

            throw $exception;
        }
    }

    public function update(
        string $supplierBillId,
        array $data
    ): SupplierBill {
        try {
            return DB::transaction(
                function () use (
                    $supplierBillId,
                    $data
                ): SupplierBill {
                    $bill =
                        $this->locked(
                            $supplierBillId
                        );

                    if (
                        $bill->status
                        !== 'DRAFT'
                    ) {
                        throw new SupplierBillStateConflictException(
                            'Hanya Tagihan Pemasok berstatus Draf yang dapat diubah.'
                        );
                    }

                    if (
                        array_key_exists(
                            'supplier_invoice_number',
                            $data
                        )
                    ) {
                        $supplierInvoiceNumber =
                            $this->normalizeOptionalText(
                                $data[
                                    'supplier_invoice_number'
                                ]
                            );

                        $this->assertSupplierInvoiceAvailable(
                            $bill->supplier_id,
                            $supplierInvoiceNumber,
                            $bill->id
                        );

                        $bill
                            ->supplier_invoice_number =
                            $supplierInvoiceNumber;
                    }

                    if (
                        array_key_exists(
                            'bill_date',
                            $data
                        )
                    ) {
                        $supplier =
                            $this->supplier(
                                $bill->supplier_id
                            );

                        $bill->bill_date =
                            $data['bill_date'];

                        $bill->due_date =
                            CarbonImmutable::parse(
                                $data['bill_date']
                            )
                                ->addDays(
                                    $supplier
                                        ->payment_terms_days
                                )
                                ->toDateString();
                    }

                    if (
                        array_key_exists(
                            'notes',
                            $data
                        )
                    ) {
                        $bill->notes =
                            $data['notes'];
                    }

                    $bill->save();

                    return $this->findOrFail(
                        $bill->id
                    );
                },
                3
            );
        } catch (QueryException $exception) {
            $this->translateUniqueConflict(
                $exception
            );

            throw $exception;
        }
    }

    public function post(
        string $supplierBillId
    ): SupplierBill {
        return DB::transaction(
            function () use (
                $supplierBillId
            ): SupplierBill {
                $bill =
                    $this->locked(
                        $supplierBillId
                    );

                if (
                    $bill->status
                    !== 'DRAFT'
                ) {
                    throw new SupplierBillStateConflictException(
                        'Hanya Tagihan Pemasok berstatus Draf yang dapat dicatat.'
                    );
                }

                $receipt =
                    $this->lockedReceipt(
                        $bill
                            ->goods_receipt_id
                    );

                if (
                    $receipt->status
                    !== 'POSTED'
                ) {
                    throw new SupplierBillStateConflictException(
                        'Penerimaan sumber sudah tidak berstatus Dicatat.'
                    );
                }

                $from =
                    $bill->status;

                $bill->status =
                    'POSTED';

                $bill->posted_by_user_id =
                    $this
                        ->tenantContext
                        ->userId();

                $bill->posted_at =
                    now();

                $bill->save();

                $this->appendHistory(
                    $bill,
                    $from,
                    'POSTED',
                    'POSTED'
                );

                return $this->findOrFail(
                    $bill->id
                );
            },
            3
        );
    }

    public function cancel(
        string $supplierBillId,
        string $reason
    ): SupplierBill {
        return DB::transaction(
            function () use (
                $supplierBillId,
                $reason
            ): SupplierBill {
                $bill =
                    $this->locked(
                        $supplierBillId
                    );

                if (
                    ! in_array(
                        $bill->status,
                        [
                            'DRAFT',
                            'POSTED',
                        ],
                        true
                    )
                ) {
                    throw new SupplierBillStateConflictException(
                        'Tagihan Pemasok pada status ini tidak dapat dibatalkan.'
                    );
                }

                if (
                    $this->hasActivePaymentAllocation(
                        $bill->id
                    )
                ) {
                    throw new SupplierBillStateConflictException(
                        'Tagihan Pemasok yang sudah memiliki pembayaran tidak dapat dibatalkan.'
                    );
                }

                $from =
                    $bill->status;

                $bill->status =
                    'CANCELLED';

                $bill->cancelled_by_user_id =
                    $this
                        ->tenantContext
                        ->userId();

                $bill->cancelled_at =
                    now();

                $bill->cancellation_reason =
                    trim($reason);

                $bill->save();

                $this->appendHistory(
                    $bill,
                    $from,
                    'CANCELLED',
                    'CANCELLED',
                    trim($reason)
                );

                return $this->findOrFail(
                    $bill->id
                );
            },
            3
        );
    }

    private function totalsFromReceipt(
        GoodsReceipt $receipt,
        PurchaseOrder $purchaseOrder
    ): array {
        $receiptItems =
            GoodsReceiptItem::query()
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
                ->with(
                    'purchaseOrderItem'
                )
                ->get();

        if ($receiptItems->isEmpty()) {
            throw new SupplierBillStateConflictException(
                'Penerimaan tidak memiliki item yang dapat ditagihkan.'
            );
        }

        $subtotal =
            '0.000000';

        $discount =
            '0.000000';

        $tax =
            '0.000000';

        foreach (
            $receiptItems as $index => $receiptItem
        ) {
            $poItem =
                $receiptItem
                    ->purchaseOrderItem;

            if (
                ! $poItem
                || $poItem
                    ->purchase_order_id
                    !== $purchaseOrder->id
            ) {
                throw ValidationException::withMessages([
                    "items.{$index}" =>
                        'Item penerimaan tidak sesuai dengan Pesanan Pembelian.',
                ]);
            }

            $ordered =
                (string) $poItem->quantity;

            $received =
                (string)
                    $receiptItem
                        ->quantity_received;

            if (
                bccomp(
                    $ordered,
                    '0',
                    4
                ) <= 0
            ) {
                throw new SupplierBillStateConflictException(
                    'Jumlah item Pesanan Pembelian tidak valid.'
                );
            }

            $ratio =
                bcdiv(
                    $received,
                    $ordered,
                    8
                );

            $subtotal =
                bcadd(
                    $subtotal,
                    bcmul(
                        $received,
                        (string)
                            $poItem
                                ->unit_price,
                        6
                    ),
                    6
                );

            $discount =
                bcadd(
                    $discount,
                    bcmul(
                        (string)
                            $poItem
                                ->discount_amount,
                        $ratio,
                        6
                    ),
                    6
                );

            $tax =
                bcadd(
                    $tax,
                    bcmul(
                        (string)
                            $poItem
                                ->tax_amount,
                        $ratio,
                        6
                    ),
                    6
                );
        }

        $total =
            bcadd(
                bcsub(
                    $subtotal,
                    $discount,
                    6
                ),
                $tax,
                6
            );

        return [
            'subtotal' =>
                $this->money(
                    $subtotal
                ),

            'discount_total' =>
                $this->money(
                    $discount
                ),

            'tax_total' =>
                $this->money(
                    $tax
                ),

            'total' =>
                $this->money(
                    $total
                ),
        ];
    }

    private function money(
        string $value
    ): string {
        return BigDecimal::of(
            $value
        )
            ->toScale(
                2,
                RoundingMode::HalfUp
            )
            ->__toString();
    }

    private function assertReceiptAvailable(
        string $goodsReceiptId
    ): void {
        $exists =
            $this->baseQuery()
                ->where(
                    'goods_receipt_id',
                    $goodsReceiptId
                )
                ->where(
                    'status',
                    '<>',
                    'CANCELLED'
                )
                ->exists();

        if ($exists) {
            throw new SupplierBillStateConflictException(
                'Penerimaan ini sudah memiliki Tagihan Pemasok aktif.'
            );
        }
    }

    private function assertSupplierInvoiceAvailable(
        string $supplierId,
        ?string $supplierInvoiceNumber,
        ?string $exceptBillId = null
    ): void {
        if (
            $supplierInvoiceNumber
            === null
        ) {
            return;
        }

        $query =
            $this->baseQuery()
                ->where(
                    'supplier_id',
                    $supplierId
                )
                ->where(
                    'supplier_invoice_number',
                    $supplierInvoiceNumber
                )
                ->where(
                    'status',
                    '<>',
                    'CANCELLED'
                );

        if ($exceptBillId) {
            $query->where(
                'id',
                '<>',
                $exceptBillId
            );
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'supplier_invoice_number' =>
                    'Nomor invoice pemasok sudah digunakan pada Tagihan Pemasok aktif.',
            ]);
        }
    }

    private function withPaymentAmounts(
        $query
    ) {
        return $query
            ->select('supplier_bills.*')
            ->selectSub(
                function ($subquery): void {
                    $subquery
                        ->from(
                            'supplier_payment_allocations as spa'
                        )
                        ->join(
                            'supplier_payments as sp',
                            'sp.id',
                            '=',
                            'spa.supplier_payment_id'
                        )
                        ->whereColumn(
                            'spa.supplier_bill_id',
                            'supplier_bills.id'
                        )
                        ->whereColumn(
                            'spa.tenant_id',
                            'supplier_bills.tenant_id'
                        )
                        ->whereColumn(
                            'spa.business_id',
                            'supplier_bills.business_id'
                        )
                        ->whereColumn(
                            'sp.tenant_id',
                            'supplier_bills.tenant_id'
                        )
                        ->whereColumn(
                            'sp.business_id',
                            'supplier_bills.business_id'
                        )
                        ->where(
                            'sp.status',
                            'POSTED'
                        )
                        ->selectRaw(
                            'COALESCE(SUM(spa.amount), 0)'
                        );
                },
                'paid_amount'
            );
    }

    private function hasActivePaymentAllocation(
        string $supplierBillId
    ): bool {
        return DB::table(
            'supplier_payment_allocations as spa'
        )
            ->join(
                'supplier_payments as sp',
                'sp.id',
                '=',
                'spa.supplier_payment_id'
            )
            ->where(
                'spa.tenant_id',
                $this
                    ->tenantContext
                    ->tenantId()
            )
            ->where(
                'spa.business_id',
                $this
                    ->businessContext
                    ->businessId()
            )
            ->where(
                'spa.supplier_bill_id',
                $supplierBillId
            )
            ->where(
                'sp.status',
                'POSTED'
            )
            ->exists();
    }

    private function appendHistory(
        SupplierBill $bill,
        ?string $from,
        string $to,
        string $action,
        ?string $reason = null
    ): void {
        SupplierBillStatusHistory::query()
            ->create([
                'id' =>
                    (string) Str::ulid(),

                'tenant_id' =>
                    $bill->tenant_id,

                'business_id' =>
                    $bill->business_id,

                'supplier_bill_id' =>
                    $bill->id,

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

    private function baseQuery()
    {
        return SupplierBill::query()
            ->where(
                'tenant_id',
                $this
                    ->tenantContext
                    ->tenantId()
            )
            ->where(
                'business_id',
                $this
                    ->businessContext
                    ->businessId()
            );
    }

    private function locked(
        string $supplierBillId
    ): SupplierBill {
        return $this->baseQuery()
            ->where(
                'id',
                $supplierBillId
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockedReceipt(
        string $goodsReceiptId
    ): GoodsReceipt {
        return GoodsReceipt::query()
            ->where(
                'tenant_id',
                $this
                    ->tenantContext
                    ->tenantId()
            )
            ->where(
                'business_id',
                $this
                    ->businessContext
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
                $this
                    ->tenantContext
                    ->tenantId()
            )
            ->where(
                'business_id',
                $this
                    ->businessContext
                    ->businessId()
            )
            ->where(
                'id',
                $purchaseOrderId
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function supplier(
        string $supplierId
    ): Supplier {
        return Supplier::query()
            ->where(
                'tenant_id',
                $this
                    ->tenantContext
                    ->tenantId()
            )
            ->where(
                'business_id',
                $this
                    ->businessContext
                    ->businessId()
            )
            ->where(
                'id',
                $supplierId
            )
            ->firstOrFail();
    }

    private function defaultBillDate(): string
    {
        $timezone =
            DB::table('tenants')
                ->where(
                    'id',
                    $this
                        ->tenantContext
                        ->tenantId()
                )
                ->value('timezone')
            ?? config(
                'app.timezone',
                'UTC'
            );

        return CarbonImmutable::now(
            $timezone
        )->toDateString();
    }

    private function normalizeOptionalText(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value =
            trim($value);

        return $value === ''
            ? null
            : $value;
    }

    private function translateUniqueConflict(
        QueryException $exception
    ): void {
        if (
            (string) $exception->getCode()
            !== '23505'
        ) {
            return;
        }

        $message =
            $exception->getMessage();

        if (
            str_contains(
                $message,
                'supplier_bills_active_receipt_unique'
            )
        ) {
            throw new SupplierBillStateConflictException(
                'Penerimaan ini sudah memiliki Tagihan Pemasok aktif.'
            );
        }

        if (
            str_contains(
                $message,
                'supplier_bills_active_supplier_invoice_unique'
            )
        ) {
            throw ValidationException::withMessages([
                'supplier_invoice_number' =>
                    'Nomor invoice pemasok sudah digunakan pada Tagihan Pemasok aktif.',
            ]);
        }
    }
}
