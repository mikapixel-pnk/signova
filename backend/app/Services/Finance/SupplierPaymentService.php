<?php

namespace App\Services\Finance;

use App\Exceptions\Finance\SupplierPaymentStateConflictException;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\SupplierPaymentStatusHistory;
use App\Services\Document\DocumentNumberService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
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
        return $this->baseQuery()
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
                                    'payment_number',
                                    'ilike',
                                    $term
                                )
                                ->orWhere(
                                    'reference',
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
            ->with(
                $this->relations()
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $supplierPaymentId
    ): SupplierPayment {
        return $this->baseQuery()
            ->where(
                'id',
                $supplierPaymentId
            )
            ->with(
                $this->relations()
            )
            ->firstOrFail();
    }

    public function create(
        array $data
    ): SupplierPayment {
        return DB::transaction(
            function () use (
                $data
            ): SupplierPayment {
                $this->cashAccount(
                    $data[
                        'cash_account_id'
                    ],
                    false
                );

                $context =
                    $this->allocationContext(
                        $data[
                            'allocations'
                        ]
                    );

                $payment =
                    SupplierPayment::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $this
                                    ->tenantContext
                                    ->tenantId(),

                            'business_id' =>
                                $this
                                    ->businessContext
                                    ->businessId(),

                            'payment_number' =>
                                $this
                                    ->documentNumberService
                                    ->nextSupplierPaymentNumber(),

                            'supplier_id' =>
                                $context[
                                    'supplier_id'
                                ],

                            'cash_account_id' =>
                                $data[
                                    'cash_account_id'
                                ],

                            'status' =>
                                'DRAFT',

                            'currency' =>
                                'IDR',

                            'amount' =>
                                $context[
                                    'total'
                                ],

                            'paid_at' =>
                                $data[
                                    'paid_at'
                                ]
                                ?? null,

                            'reference' =>
                                $data[
                                    'reference'
                                ]
                                ?? null,

                            'notes' =>
                                $data[
                                    'notes'
                                ]
                                ?? null,

                            'created_by_user_id' =>
                                $this
                                    ->tenantContext
                                    ->userId(),
                        ]);

                $this->replaceAllocations(
                    $payment,
                    $data[
                        'allocations'
                    ]
                );

                $this->appendHistory(
                    $payment,
                    null,
                    'DRAFT',
                    'CREATED'
                );

                return $this->findOrFail(
                    $payment->id
                );
            },
            3
        );
    }

    public function update(
        string $supplierPaymentId,
        array $data
    ): SupplierPayment {
        return DB::transaction(
            function () use (
                $supplierPaymentId,
                $data
            ): SupplierPayment {
                $payment =
                    $this->locked(
                        $supplierPaymentId
                    );

                if (
                    $payment->status
                    !== 'DRAFT'
                ) {
                    throw new SupplierPaymentStateConflictException(
                        'Hanya Pembayaran Pemasok berstatus Draf yang dapat diubah.'
                    );
                }

                if (
                    array_key_exists(
                        'cash_account_id',
                        $data
                    )
                ) {
                    $this->cashAccount(
                        $data[
                            'cash_account_id'
                        ],
                        false
                    );

                    $payment
                        ->cash_account_id =
                        $data[
                            'cash_account_id'
                        ];
                }

                if (
                    array_key_exists(
                        'allocations',
                        $data
                    )
                ) {
                    $context =
                        $this->allocationContext(
                            $data[
                                'allocations'
                            ]
                        );

                    $payment->supplier_id =
                        $context[
                            'supplier_id'
                        ];

                    $payment->amount =
                        $context[
                            'total'
                        ];

                    $this->replaceAllocations(
                        $payment,
                        $data[
                            'allocations'
                        ]
                    );
                }

                foreach (
                    [
                        'paid_at',
                        'reference',
                        'notes',
                    ] as $field
                ) {
                    if (
                        array_key_exists(
                            $field,
                            $data
                        )
                    ) {
                        $payment->{$field} =
                            $data[$field];
                    }
                }

                $payment->save();

                return $this->findOrFail(
                    $payment->id
                );
            },
            3
        );
    }

    public function post(
        string $supplierPaymentId
    ): SupplierPayment {
        return DB::transaction(
            function () use (
                $supplierPaymentId
            ): SupplierPayment {
                $payment =
                    $this->locked(
                        $supplierPaymentId
                    );

                if (
                    $payment->status
                    !== 'DRAFT'
                ) {
                    throw new SupplierPaymentStateConflictException(
                        'Hanya Pembayaran Pemasok berstatus Draf yang dapat dicatat.'
                    );
                }

                $account =
                    $this->cashAccount(
                        $payment
                            ->cash_account_id,
                        true
                    );

                if (
                    $account->currency
                    !== $payment->currency
                ) {
                    throw new SupplierPaymentStateConflictException(
                        'Mata uang rekening tidak sesuai dengan Pembayaran Pemasok.'
                    );
                }

                $allocationRows =
                    SupplierPaymentAllocation::query()
                        ->where(
                            'tenant_id',
                            $payment->tenant_id
                        )
                        ->where(
                            'business_id',
                            $payment->business_id
                        )
                        ->where(
                            'supplier_payment_id',
                            $payment->id
                        )
                        ->orderBy(
                            'supplier_bill_id'
                        )
                        ->get();

                if (
                    $allocationRows->isEmpty()
                ) {
                    throw new SupplierPaymentStateConflictException(
                        'Pembayaran Pemasok belum memiliki alokasi tagihan.'
                    );
                }

                $allocations =
                    $allocationRows
                        ->map(
                            fn ($allocation) => [
                                'supplier_bill_id' =>
                                    $allocation
                                        ->supplier_bill_id,

                                'amount' =>
                                    (string)
                                        $allocation
                                            ->amount,
                            ]
                        )
                        ->all();

                $context =
                    $this->allocationContext(
                        $allocations
                    );

                if (
                    $context[
                        'supplier_id'
                    ] !== $payment->supplier_id
                ) {
                    throw new SupplierPaymentStateConflictException(
                        'Pemasok pada alokasi pembayaran tidak konsisten.'
                    );
                }

                if (
                    bccomp(
                        $context['total'],
                        (string)
                            $payment->amount,
                        2
                    ) !== 0
                ) {
                    throw new SupplierPaymentStateConflictException(
                        'Jumlah alokasi pembayaran tidak konsisten.'
                    );
                }

                $postedAt =
                    now();

                $paidAt =
                    $payment->paid_at
                    ?? $postedAt;

                $cashTransaction =
                    CashTransaction::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $payment
                                    ->tenant_id,

                            'business_id' =>
                                $payment
                                    ->business_id,

                            'cash_account_id' =>
                                $account->id,

                            'direction' =>
                                'OUT',

                            'amount' =>
                                $payment->amount,

                            'currency' =>
                                $payment->currency,

                            'occurred_at' =>
                                $paidAt,

                            'source_type' =>
                                'SUPPLIER_PAYMENT',

                            'source_id' =>
                                $payment->id,

                            'reference' =>
                                $payment->reference,

                            'description' =>
                                'Pembayaran pemasok '
                                . $payment
                                    ->payment_number,

                            'reversal_of_transaction_id' =>
                                null,

                            'created_by_user_id' =>
                                $this
                                    ->tenantContext
                                    ->userId(),
                        ]);

                $payment->status =
                    'POSTED';

                $payment->paid_at =
                    $paidAt;

                $payment->cash_transaction_id =
                    $cashTransaction->id;

                $payment->posted_by_user_id =
                    $this
                        ->tenantContext
                        ->userId();

                $payment->posted_at =
                    $postedAt;

                $payment->save();

                $this->appendHistory(
                    $payment,
                    'DRAFT',
                    'POSTED',
                    'POSTED'
                );

                foreach (
                    $context['bills']
                    as $bill
                ) {
                    $this->recalculateBillStatus(
                        $bill,
                        'PAYMENT_APPLIED'
                    );
                }

                return $this->findOrFail(
                    $payment->id
                );
            },
            3
        );
    }

    public function reverse(
        string $supplierPaymentId,
        string $reason
    ): SupplierPayment {
        return DB::transaction(
            function () use (
                $supplierPaymentId,
                $reason
            ): SupplierPayment {
                $payment =
                    $this->locked(
                        $supplierPaymentId
                    );

                if (
                    $payment->status
                    !== 'POSTED'
                ) {
                    throw new SupplierPaymentStateConflictException(
                        'Hanya Pembayaran Pemasok yang sudah Tercatat yang dapat dikoreksi.'
                    );
                }

                $original =
                    CashTransaction::query()
                        ->where(
                            'tenant_id',
                            $payment->tenant_id
                        )
                        ->where(
                            'business_id',
                            $payment->business_id
                        )
                        ->where(
                            'id',
                            $payment
                                ->cash_transaction_id
                        )
                        ->where(
                            'source_type',
                            'SUPPLIER_PAYMENT'
                        )
                        ->where(
                            'source_id',
                            $payment->id
                        )
                        ->lockForUpdate()
                        ->first();

                if ($original === null) {
                    throw new SupplierPaymentStateConflictException(
                        'Ledger Pembayaran Pemasok tidak ditemukan.'
                    );
                }

                if (
                    $original->direction
                    !== 'OUT'
                    || $original
                        ->cash_account_id
                        !== $payment
                            ->cash_account_id
                    || $original->currency
                        !== $payment->currency
                    || bccomp(
                        (string)
                            $original->amount,
                        (string)
                            $payment->amount,
                        2
                    ) !== 0
                ) {
                    throw new SupplierPaymentStateConflictException(
                        'Ledger Pembayaran Pemasok tidak konsisten.'
                    );
                }

                $billIds =
                    SupplierPaymentAllocation::query()
                        ->where(
                            'tenant_id',
                            $payment->tenant_id
                        )
                        ->where(
                            'business_id',
                            $payment->business_id
                        )
                        ->where(
                            'supplier_payment_id',
                            $payment->id
                        )
                        ->pluck(
                            'supplier_bill_id'
                        )
                        ->unique()
                        ->sort()
                        ->values();

                $bills =
                    SupplierBill::query()
                        ->where(
                            'tenant_id',
                            $payment->tenant_id
                        )
                        ->where(
                            'business_id',
                            $payment->business_id
                        )
                        ->whereIn(
                            'id',
                            $billIds
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                if (
                    $bills->count()
                    !== $billIds->count()
                ) {
                    throw new SupplierPaymentStateConflictException(
                        'Tagihan Pemasok pada pembayaran tidak lengkap.'
                    );
                }

                $reversedAt =
                    now();

                $reversal =
                    CashTransaction::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $payment
                                    ->tenant_id,

                            'business_id' =>
                                $payment
                                    ->business_id,

                            'cash_account_id' =>
                                $original
                                    ->cash_account_id,

                            'direction' =>
                                'IN',

                            'amount' =>
                                $original
                                    ->amount,

                            'currency' =>
                                $original
                                    ->currency,

                            'occurred_at' =>
                                $reversedAt,

                            'source_type' =>
                                'SUPPLIER_PAYMENT_REVERSAL',

                            'source_id' =>
                                $payment->id,

                            'reference' =>
                                $payment->reference,

                            'description' =>
                                'Koreksi Pembayaran Pemasok: '
                                . trim($reason),

                            'reversal_of_transaction_id' =>
                                $original->id,

                            'created_by_user_id' =>
                                $this
                                    ->tenantContext
                                    ->userId(),
                        ]);

                $payment->status =
                    'REVERSED';

                $payment
                    ->reversal_cash_transaction_id =
                    $reversal->id;

                $payment->reversed_by_user_id =
                    $this
                        ->tenantContext
                        ->userId();

                $payment->reversed_at =
                    $reversedAt;

                $payment->reversal_reason =
                    trim($reason);

                $payment->save();

                $this->appendHistory(
                    $payment,
                    'POSTED',
                    'REVERSED',
                    'REVERSED',
                    trim($reason)
                );

                foreach ($bills as $bill) {
                    $this->recalculateBillStatus(
                        $bill,
                        'PAYMENT_REVERSED'
                    );
                }

                return $this->findOrFail(
                    $payment->id
                );
            },
            3
        );
    }

    private function allocationContext(
        array $allocations
    ): array {
        $billIds =
            collect($allocations)
                ->pluck(
                    'supplier_bill_id'
                )
                ->map(
                    fn ($id) =>
                        (string) $id
                )
                ->unique()
                ->sort()
                ->values();

        if (
            $billIds->count()
            !== count($allocations)
        ) {
            throw ValidationException::withMessages([
                'allocations' =>
                    'Tagihan Pemasok tidak boleh dialokasikan lebih dari satu kali.',
            ]);
        }

        $bills =
            SupplierBill::query()
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
                ->whereIn(
                    'id',
                    $billIds
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

        if (
            $bills->count()
            !== $billIds->count()
        ) {
            throw ValidationException::withMessages([
                'allocations' =>
                    'Salah satu Tagihan Pemasok tidak ditemukan.',
            ]);
        }

        $supplierId = null;
        $total = '0.00';

        foreach (
            $allocations as $index => $allocation
        ) {
            $bill =
                $bills->get(
                    (string)
                        $allocation[
                            'supplier_bill_id'
                        ]
                );

            if (
                ! in_array(
                    $bill->status,
                    [
                        'POSTED',
                        'PARTIALLY_PAID',
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.supplier_bill_id" =>
                        'Hanya Tagihan Pemasok yang sudah Tercatat dan masih memiliki sisa utang yang dapat dibayar.',
                ]);
            }

            if ($supplierId === null) {
                $supplierId =
                    $bill->supplier_id;
            } elseif (
                $supplierId
                !== $bill->supplier_id
            ) {
                throw ValidationException::withMessages([
                    'allocations' =>
                        'Satu Pembayaran Pemasok hanya dapat dialokasikan ke tagihan dari pemasok yang sama.',
                ]);
            }

            $amount =
                $this->money(
                    $allocation[
                        'amount'
                    ]
                );

            if (
                bccomp(
                    $amount,
                    '0.00',
                    2
                ) <= 0
            ) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.amount" =>
                        'Jumlah alokasi harus lebih besar dari nol.',
                ]);
            }

            $outstanding =
                $this->billOutstanding(
                    $bill
                );

            if (
                bccomp(
                    $amount,
                    $outstanding,
                    2
                ) > 0
            ) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.amount" =>
                        'Jumlah pembayaran melebihi sisa Utang Usaha.',
                ]);
            }

            $total =
                bcadd(
                    $total,
                    $amount,
                    2
                );
        }

        if ($supplierId === null) {
            throw ValidationException::withMessages([
                'allocations' =>
                    'Minimal satu Tagihan Pemasok wajib dipilih.',
            ]);
        }

        return [
            'supplier_id' =>
                $supplierId,

            'total' =>
                $total,

            'bills' =>
                $bills->values(),
        ];
    }

    private function billOutstanding(
        SupplierBill $bill
    ): string {
        $paid =
            DB::table(
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
                    $bill->tenant_id
                )
                ->where(
                    'spa.business_id',
                    $bill->business_id
                )
                ->where(
                    'spa.supplier_bill_id',
                    $bill->id
                )
                ->where(
                    'sp.status',
                    'POSTED'
                )
                ->sum(
                    'spa.amount'
                );

        $outstanding =
            bcsub(
                (string)
                    $bill->total,
                (string) $paid,
                2
            );

        return bccomp(
            $outstanding,
            '0.00',
            2
        ) < 0
            ? '0.00'
            : $outstanding;
    }

    private function replaceAllocations(
        SupplierPayment $payment,
        array $allocations
    ): void {
        SupplierPaymentAllocation::query()
            ->where(
                'tenant_id',
                $payment->tenant_id
            )
            ->where(
                'business_id',
                $payment->business_id
            )
            ->where(
                'supplier_payment_id',
                $payment->id
            )
            ->delete();

        foreach ($allocations as $allocation) {
            SupplierPaymentAllocation::query()
                ->create([
                    'id' =>
                        (string) Str::ulid(),

                    'tenant_id' =>
                        $payment->tenant_id,

                    'business_id' =>
                        $payment->business_id,

                    'supplier_payment_id' =>
                        $payment->id,

                    'supplier_bill_id' =>
                        $allocation[
                            'supplier_bill_id'
                        ],

                    'amount' =>
                        $this->money(
                            $allocation[
                                'amount'
                            ]
                        ),
                ]);
        }
    }

    private function money(
        mixed $value
    ): string {
        return BigDecimal::of(
            (string) (
                $value
                ?? '0'
            )
        )
            ->toScale(
                2,
                RoundingMode::HalfUp
            )
            ->__toString();
    }

    private function recalculateBillStatus(
        SupplierBill $bill,
        string $action
    ): void {
        $paid =
            DB::table(
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
                    $bill->tenant_id
                )
                ->where(
                    'spa.business_id',
                    $bill->business_id
                )
                ->where(
                    'spa.supplier_bill_id',
                    $bill->id
                )
                ->where(
                    'sp.status',
                    'POSTED'
                )
                ->sum(
                    'spa.amount'
                );

        $paid =
            $this->money(
                $paid
            );

        if (
            bccomp(
                $paid,
                (string)
                    $bill->total,
                2
            ) >= 0
        ) {
            $newStatus =
                'PAID';
        } elseif (
            bccomp(
                $paid,
                '0.00',
                2
            ) > 0
        ) {
            $newStatus =
                'PARTIALLY_PAID';
        } else {
            $newStatus =
                'POSTED';
        }

        if (
            $bill->status
            === $newStatus
        ) {
            return;
        }

        $fromStatus =
            $bill->status;

        $bill->status =
            $newStatus;

        $bill->save();

        DB::table(
            'supplier_bill_status_history'
        )->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $bill->tenant_id,

            'business_id' =>
                $bill->business_id,

            'supplier_bill_id' =>
                $bill->id,

            'from_status' =>
                $fromStatus,

            'to_status' =>
                $newStatus,

            'action' =>
                $action,

            'actor_user_id' =>
                $this
                    ->tenantContext
                    ->userId(),

            'reason' =>
                null,

            'created_at' =>
                now(),
        ]);
    }

    private function cashAccount(
        string $cashAccountId,
        bool $requireActive
    ): CashAccount {
        $account =
            CashAccount::query()
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
                    $cashAccountId
                )
                ->lockForUpdate()
                ->firstOrFail();

        if (
            $requireActive
            && $account->status
                !== 'ACTIVE'
        ) {
            throw new SupplierPaymentStateConflictException(
                'Pembayaran Pemasok hanya dapat menggunakan rekening Kas & Bank yang Aktif.'
            );
        }

        return $account;
    }

    private function appendHistory(
        SupplierPayment $payment,
        ?string $from,
        string $to,
        string $action,
        ?string $reason = null
    ): void {
        SupplierPaymentStatusHistory::query()
            ->create([
                'id' =>
                    (string) Str::ulid(),

                'tenant_id' =>
                    $payment->tenant_id,

                'business_id' =>
                    $payment->business_id,

                'supplier_payment_id' =>
                    $payment->id,

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
        return SupplierPayment::query()
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
        string $supplierPaymentId
    ): SupplierPayment {
        return $this->baseQuery()
            ->where(
                'id',
                $supplierPaymentId
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function relations(): array
    {
        return [
            'supplier',
            'cashAccount',
            'allocations.bill',
        ];
    }
}
