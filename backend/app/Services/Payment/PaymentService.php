<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\InvalidPaymentTransitionException;
use App\Exceptions\Payment\PaymentAllocationConflictException;
use App\Exceptions\Payment\PaymentEvidenceLockedException;
use App\Models\CashTransaction;
use App\Models\FileAsset;
use App\Models\Invoice;
use App\Models\InvoiceStatusHistory;
use App\Models\PaymentAllocation;
use App\Models\PaymentReversal;
use App\Models\Payment;
use App\Services\File\FileService;
use App\Services\Settings\PaymentSettingService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly PaymentSettingService $paymentSettingService,
        private readonly FileService $fileService
    ) {
    }

    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?string $method = null,
        ?string $customerId = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->baseQuery()
            ->with([
                'customer',
                'cashAccount',
                'evidenceFile',
                'intendedInvoice',
            ])
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
                                    'reference',
                                    'ILIKE',
                                    '%' . $search . '%'
                                )
                                ->orWhereHas(
                                    'customer',
                                    function (
                                        Builder $query
                                    ) use ($search): void {
                                        $query->where(
                                            'name',
                                            'ILIKE',
                                            '%' . $search . '%'
                                        );
                                    }
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
                $method,
                fn (
                    Builder $query,
                    string $method
                ) =>
                    $query->where(
                        'method',
                        $method
                    )
            )
            ->when(
                $customerId,
                fn (
                    Builder $query,
                    string $customerId
                ) =>
                    $query->where(
                        'customer_id',
                        $customerId
                    )
            )
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $paymentId
    ): Payment {
        return $this->baseQuery()
            ->with([
                'customer',
                'cashAccount',
                'evidenceFile',
                'intendedInvoice',
            ])
            ->where(
                'id',
                $paymentId
            )
            ->firstOrFail();
    }

    public function listForInvoice(
        string $invoiceId
    ): array {
        Invoice::query()
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
                $invoiceId
            )
            ->firstOrFail();

        return PaymentAllocation::query()
            ->join(
                'payments',
                function ($join): void {
                    $join
                        ->on(
                            'payments.id',
                            '=',
                            'payment_allocations.payment_id'
                        )
                        ->on(
                            'payments.tenant_id',
                            '=',
                            'payment_allocations.tenant_id'
                        )
                        ->on(
                            'payments.business_id',
                            '=',
                            'payment_allocations.business_id'
                        );
                }
            )
            ->where(
                'payment_allocations.tenant_id',
                $this->tenantContext
                    ->tenantId()
            )
            ->where(
                'payment_allocations.business_id',
                $this->businessContext
                    ->businessId()
            )
            ->where(
                'payment_allocations.invoice_id',
                $invoiceId
            )
            ->orderByDesc(
                'payments.paid_at'
            )
            ->orderByDesc(
                'payment_allocations.created_at'
            )
            ->get([
                'payment_allocations.id as allocation_id',
                'payment_allocations.allocated_amount',
                'payments.id as payment_id',
                'payments.amount as payment_amount',
                'payments.status',
                'payments.method',
                'payments.paid_at',
                'payments.reference',
                'payments.evidence_file_id',
                'payments.created_at',
            ])
            ->map(
                fn ($row) => [
                    'allocation_id' =>
                        $row->allocation_id,

                    'payment_id' =>
                        $row->payment_id,

                    'allocated_amount' =>
                        $row->allocated_amount,

                    'payment_amount' =>
                        $row->payment_amount,

                    'status' =>
                        $row->status,

                    'method' =>
                        $row->method,

                    'paid_at' =>
                        $row->paid_at,

                    'reference' =>
                        $row->reference,

                    'has_evidence' =>
                        $row->evidence_file_id
                        !== null,

                    'created_at' =>
                        $row->created_at,
                ]
            )
            ->values()
            ->all();
    }

    public function recordForInvoice(
        string $invoiceId,
        array $data
    ): array {
        return DB::transaction(
            function () use (
                $invoiceId,
                $data
            ): array {
                $invoice =
                    Invoice::query()
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
                            $invoiceId
                        )
                        ->firstOrFail();

                $payment =
                    $this->create([
                        'customer_id' =>
                            $invoice->customer_id,

                        'cash_account_id' =>
                            $data['cash_account_id'],

                        'amount' =>
                            $data['amount'],

                        'currency' =>
                            $invoice->currency,

                        'paid_at' =>
                            $data['paid_at'],

                        'method' =>
                            $data['method'],

                        'reference' =>
                            $data['reference']
                            ?? null,
                    ]);

                $this->verify(
                    $payment->id
                );

                return $this->allocate(
                    $payment->id,
                    [
                        'invoice_id' =>
                            $invoice->id,

                        'amount' =>
                            $data['amount'],
                    ]
                );
            },
            3
        );
    }

    public function create(
        array $data
    ): Payment {
        $payment =
            Payment::query()->create([
                'id' =>
                    (string) Str::ulid(),

                'tenant_id' =>
                    $this->tenantContext
                        ->tenantId(),

                'business_id' =>
                    $this->businessContext
                        ->businessId(),

                'customer_id' =>
                    $data['customer_id'],

                'cash_account_id' =>
                    $data['cash_account_id'],

                'amount' =>
                    $data['amount'],

                'currency' =>
                    $data['currency']
                    ?? 'IDR',

                'paid_at' =>
                    $data['paid_at'],

                'method' =>
                    $data['method'],

                'status' =>
                    'PENDING',

                'reference' =>
                    $data['reference']
                    ?? null,

                'evidence_file_id' =>
                    null,

                'provider' =>
                    null,

                'provider_reference' =>
                    null,

                'provider_transaction_id' =>
                    null,

                'idempotency_key' =>
                    null,

                'created_by_user_id' =>
                    $this->tenantContext
                        ->userId(),
            ]);

        return $this->findOrFail(
            $payment->id
        );
    }

    public function verify(
        string $paymentId
    ): Payment {
        return DB::transaction(
            function () use (
                $paymentId
            ): Payment {
                /*
                 * Canonical lock order:
                 * 1. payment
                 * 2. intended invoice
                 *
                 * Payment public yang memiliki
                 * intended_invoice_id harus
                 * diverifikasi + dialokasikan
                 * sebagai satu financial boundary.
                 */
                $payment =
                    $this->baseQuery()
                        ->where(
                            'id',
                            $paymentId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $payment->status !== 'PENDING'
                ) {
                    throw new InvalidPaymentTransitionException(
                        $payment->status,
                        'VERIFIED'
                    );
                }

                $payment->fill([
                    'status' =>
                        'VERIFIED',

                    'verified_by_user_id' =>
                        $this->tenantContext
                            ->userId(),

                    'verified_at' =>
                        now(),

                    'rejected_by_user_id' =>
                        null,

                    'rejected_at' =>
                        null,

                    'rejection_reason' =>
                        null,
                ]);

                $payment->save();

                if ($payment->cash_account_id === null) {
                    throw new \LogicException(
                        'Rekening tujuan pembayaran belum ditentukan.'
                    );
                }

                CashTransaction::query()->create([
                    'tenant_id' =>
                        $this->tenantContext
                            ->tenantId(),

                    'business_id' =>
                        $this->businessContext
                            ->businessId(),

                    'cash_account_id' =>
                        $payment->cash_account_id,

                    'direction' =>
                        'IN',

                    'amount' =>
                        $payment->amount,

                    'currency' =>
                        $payment->currency,

                    'occurred_at' =>
                        $payment->paid_at,

                    'source_type' =>
                        'PAYMENT',

                    'source_id' =>
                        $payment->id,

                    'reference' =>
                        $payment->reference,

                    'description' =>
                        'Penerimaan pembayaran pelanggan.',

                    'reversal_of_transaction_id' =>
                        null,

                    'created_by_user_id' =>
                        $this->tenantContext
                            ->userId(),
                ]);

                /*
                 * Payment biasa tetap selesai
                 * setelah verifikasi + posting kas.
                 *
                 * Payment customer/public memiliki
                 * intended_invoice_id, sehingga
                 * seluruh amount harus dialokasikan
                 * ke Tagihan tujuan pada transaksi
                 * database yang sama.
                 *
                 * allocate() memiliki nested
                 * transaction/savepoint. Bila
                 * validasi allocation gagal,
                 * exception keluar ke transaction
                 * ini sehingga perubahan status
                 * Payment dan CashTransaction ikut
                 * rollback.
                 */
                if (
                    $payment
                        ->intended_invoice_id
                    !== null
                ) {
                    $this->allocate(
                        $payment->id,
                        [
                            'invoice_id' =>
                                $payment
                                    ->intended_invoice_id,

                            'amount' =>
                                $payment->amount,
                        ]
                    );
                }

                return $this->findOrFail(
                    $payment->id
                );
            },
            3
        );
    }


    public function reject(
        string $paymentId,
        string $reason
    ): Payment {
        return DB::transaction(
            function () use (
                $paymentId,
                $reason
            ): Payment {
                $payment =
                    $this->baseQuery()
                        ->where(
                            'id',
                            $paymentId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $payment->status !== 'PENDING'
                ) {
                    throw new InvalidPaymentTransitionException(
                        $payment->status,
                        'REJECTED'
                    );
                }

                $payment->fill([
                    'status' =>
                        'REJECTED',

                    'rejected_by_user_id' =>
                        $this->tenantContext
                            ->userId(),

                    'rejected_at' =>
                        now(),

                    'rejection_reason' =>
                        $reason,

                    'verified_by_user_id' =>
                        null,

                    'verified_at' =>
                        null,
                ]);

                $payment->save();

                return $this->findOrFail(
                    $payment->id
                );
            }
        );
    }

    public function reverse(
        string $paymentId,
        string $reason
    ): array {
        return DB::transaction(
            function () use (
                $paymentId,
                $reason
            ): array {
                /*
                 * Canonical lock order:
                 * 1. payment
                 * 2. original payment cash transaction
                 * 3. affected invoices sorted by ID
                 */
                $payment =
                    $this->baseQuery()
                        ->where(
                            'id',
                            $paymentId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $payment->status !== 'VERIFIED'
                ) {
                    throw new InvalidPaymentTransitionException(
                        $payment->status,
                        'REVERSED'
                    );
                }

                $originalCashTransaction =
                    CashTransaction::query()
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
                            'source_type',
                            'PAYMENT'
                        )
                        ->where(
                            'source_id',
                            $payment->id
                        )
                        ->lockForUpdate()
                        ->first();

                if ($originalCashTransaction === null) {
                    throw new \LogicException(
                        'Penerimaan kas pembayaran tidak ditemukan.'
                    );
                }

                if (
                    $originalCashTransaction
                        ->cash_account_id
                    !== $payment->cash_account_id
                    || $originalCashTransaction
                        ->currency
                    !== $payment->currency
                    || $this->minorUnits(
                        $originalCashTransaction->amount
                    ) !== $this->minorUnits(
                        $payment->amount
                    )
                ) {
                    throw new \LogicException(
                        'Ledger kas pembayaran tidak konsisten.'
                    );
                }

                $invoiceIds =
                    PaymentAllocation::query()
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
                            'payment_id',
                            $payment->id
                        )
                        ->orderBy(
                            'invoice_id'
                        )
                        ->pluck(
                            'invoice_id'
                        )
                        ->unique()
                        ->values();

                $invoices = collect();

                foreach ($invoiceIds as $invoiceId) {
                    $invoice =
                        Invoice::query()
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
                                $invoiceId
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $invoices->push(
                        $invoice
                    );
                }

                $reversal =
                    PaymentReversal::query()
                        ->create([
                            'tenant_id' =>
                                $this->tenantContext
                                    ->tenantId(),

                            'business_id' =>
                                $this->businessContext
                                    ->businessId(),

                            'payment_id' =>
                                $payment->id,

                            /*
                             * Starter baseline:
                             * reversal selalu FULL payment.
                             */
                            'amount' =>
                                $payment->amount,

                            'reason' =>
                                $reason,

                            'reversed_by_user_id' =>
                                $this->tenantContext
                                    ->userId(),

                            'reversed_at' =>
                                now(),
                        ]);

                CashTransaction::query()->create([
                    'tenant_id' =>
                        $this->tenantContext
                            ->tenantId(),

                    'business_id' =>
                        $this->businessContext
                            ->businessId(),

                    'cash_account_id' =>
                        $originalCashTransaction
                            ->cash_account_id,

                    'direction' =>
                        'OUT',

                    'amount' =>
                        $payment->amount,

                    'currency' =>
                        $payment->currency,

                    'occurred_at' =>
                        $reversal->reversed_at,

                    'source_type' =>
                        'PAYMENT_REVERSAL',

                    'source_id' =>
                        $reversal->id,

                    'reference' =>
                        $payment->reference,

                    'description' =>
                        'Pembalikan penerimaan pembayaran.',

                    'reversal_of_transaction_id' =>
                        $originalCashTransaction
                            ->id,

                    'created_by_user_id' =>
                        $this->tenantContext
                            ->userId(),
                ]);

                /*
                 * Status harus berubah sebelum invoice
                 * dihitung ulang, supaya allocation
                 * payment ini tidak lagi dihitung sebagai
                 * VERIFIED ledger.
                 */
                $payment->status =
                    'REVERSED';

                $payment->save();

                $affectedInvoices = [];

                foreach ($invoices as $invoice) {
                    $invoiceTotalMinor =
                        $this->minorUnits(
                            $invoice->total
                        );

                    $paidMinor =
                        $this->minorUnits(
                            PaymentAllocation::query()
                                ->join(
                                    'payments',
                                    function ($join): void {
                                        $join
                                            ->on(
                                                'payments.id',
                                                '=',
                                                'payment_allocations.payment_id'
                                            )
                                            ->on(
                                                'payments.tenant_id',
                                                '=',
                                                'payment_allocations.tenant_id'
                                            )
                                            ->on(
                                                'payments.business_id',
                                                '=',
                                                'payment_allocations.business_id'
                                            );
                                    }
                                )
                                ->where(
                                    'payment_allocations.tenant_id',
                                    $this->tenantContext
                                        ->tenantId()
                                )
                                ->where(
                                    'payment_allocations.business_id',
                                    $this->businessContext
                                        ->businessId()
                                )
                                ->where(
                                    'payment_allocations.invoice_id',
                                    $invoice->id
                                )
                                ->where(
                                    'payments.status',
                                    'VERIFIED'
                                )
                                ->sum(
                                    'payment_allocations.allocated_amount'
                                )
                        );

                    if (
                        $paidMinor < 0
                        || $paidMinor > $invoiceTotalMinor
                    ) {
                        throw new \LogicException(
                            'Ledger pembayaran tagihan tidak valid.'
                        );
                    }

                    $outstandingMinor =
                        $invoiceTotalMinor
                        - $paidMinor;

                    $this->assertReceivableInvariant(
                        $invoiceTotalMinor,
                        $paidMinor,
                        $outstandingMinor
                    );

                    $fromState =
                        $invoice->status;

                    if ($paidMinor === 0) {
                        $toState =
                            'ISSUED';
                    } elseif (
                        $outstandingMinor === 0
                    ) {
                        $toState =
                            'PAID';
                    } else {
                        $toState =
                            'PARTIALLY_PAID';
                    }

                    $invoice->fill([
                        'paid_amount' =>
                            $this->minorToDecimal(
                                $paidMinor
                            ),

                        'outstanding_amount' =>
                            $this->minorToDecimal(
                                $outstandingMinor
                            ),

                        'status' =>
                            $toState,
                    ]);

                    $invoice->save();

                    if ($fromState !== $toState) {
                        InvoiceStatusHistory::query()
                            ->create([
                                'id' =>
                                    (string) Str::ulid(),

                                'tenant_id' =>
                                    $this->tenantContext
                                        ->tenantId(),

                                'business_id' =>
                                    $invoice->business_id,

                                'invoice_id' =>
                                    $invoice->id,

                                'from_state' =>
                                    $fromState,

                                'to_state' =>
                                    $toState,

                                'actor_user_id' =>
                                    $this->tenantContext
                                        ->userId(),

                                'reason' =>
                                    $reason,

                                'source' =>
                                    'PAYMENT_REVERSAL',

                                'context' => [
                                    'payment_id' =>
                                        $payment->id,

                                    'reversal_id' =>
                                        $reversal->id,
                                ],

                                'occurred_at' =>
                                    now(),
                            ]);
                    }

                    $invoice->load([
                        'customer',
                        'statusHistory',
                    ]);

                    $affectedInvoices[] =
                        $invoice;
                }

                $payment->load([
                    'customer',
                    'cashAccount',
                    'evidenceFile',
                ]);

                return [
                    'reversal' =>
                        $reversal,

                    'payment' =>
                        $payment,

                    'invoices' =>
                        $affectedInvoices,
                ];
            },
            3
        );
    }

    public function allocate(
        string $paymentId,
        array $data
    ): array {
        return DB::transaction(
            function () use (
                $paymentId,
                $data
            ): array {
                /*
                 * Lock order wajib konsisten:
                 * 1. payment
                 * 2. invoice
                 */
                $payment =
                    $this->baseQuery()
                        ->where(
                            'id',
                            $paymentId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $payment->status !== 'VERIFIED'
                ) {
                    throw new PaymentAllocationConflictException(
                        'Hanya pembayaran VERIFIED yang dapat dialokasikan.'
                    );
                }

                $invoice =
                    Invoice::query()
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
                            $data['invoice_id']
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $invoice->customer_id
                    !== $payment->customer_id
                ) {
                    throw new PaymentAllocationConflictException(
                        'Pelanggan pembayaran dan tagihan harus sama.'
                    );
                }

                if (
                    $invoice->currency
                    !== $payment->currency
                ) {
                    throw new PaymentAllocationConflictException(
                        'Mata uang pembayaran dan tagihan harus sama.'
                    );
                }

                if (
                    ! in_array(
                        $invoice->status,
                        [
                            'ISSUED',
                            'PARTIALLY_PAID',
                            'OVERDUE',
                        ],
                        true
                    )
                ) {
                    throw new PaymentAllocationConflictException(
                        'Tagihan pada status ini tidak dapat menerima alokasi pembayaran.'
                    );
                }

                $existingAllocation =
                    PaymentAllocation::query()
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
                            'payment_id',
                            $payment->id
                        )
                        ->where(
                            'invoice_id',
                            $invoice->id
                        )
                        ->first();

                if ($existingAllocation !== null) {
                    throw new PaymentAllocationConflictException(
                        'Pembayaran ini sudah memiliki alokasi ke tagihan tersebut.'
                    );
                }

                $requestedMinor =
                    $this->minorUnits(
                        $data['amount']
                    );

                $paymentAmountMinor =
                    $this->minorUnits(
                        $payment->amount
                    );

                $allocatedPaymentMinor =
                    $this->minorUnits(
                        PaymentAllocation::query()
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
                                'payment_id',
                                $payment->id
                            )
                            ->sum(
                                'allocated_amount'
                            )
                    );

                if (
                    $allocatedPaymentMinor
                    + $requestedMinor
                    > $paymentAmountMinor
                ) {
                    throw new PaymentAllocationConflictException(
                        'Jumlah alokasi melebihi sisa pembayaran.'
                    );
                }

                /*
                 * Ledger adalah source of truth.
                 * Cache invoice tidak dipakai sebagai dasar kalkulasi.
                 */
                $invoicePaidMinor =
                    $this->minorUnits(
                        PaymentAllocation::query()
                            ->join(
                                'payments',
                                function ($join): void {
                                    $join
                                        ->on(
                                            'payments.id',
                                            '=',
                                            'payment_allocations.payment_id'
                                        )
                                        ->on(
                                            'payments.tenant_id',
                                            '=',
                                            'payment_allocations.tenant_id'
                                        )
                                        ->on(
                                            'payments.business_id',
                                            '=',
                                            'payment_allocations.business_id'
                                        );
                                }
                            )
                            ->where(
                                'payment_allocations.tenant_id',
                                $this->tenantContext
                                    ->tenantId()
                            )
                            ->where(
                                'payment_allocations.business_id',
                                $this->businessContext
                                    ->businessId()
                            )
                            ->where(
                                'payment_allocations.invoice_id',
                                $invoice->id
                            )
                            ->where(
                                'payments.status',
                                'VERIFIED'
                            )
                            ->sum(
                                'payment_allocations.allocated_amount'
                            )
                    );

                $invoiceTotalMinor =
                    $this->minorUnits(
                        $invoice->total
                    );

                $invoiceRemainingMinor =
                    $invoiceTotalMinor
                    - $invoicePaidMinor;

                if ($invoiceRemainingMinor <= 0) {
                    throw new PaymentAllocationConflictException(
                        'Tagihan ini sudah tidak memiliki sisa pembayaran.'
                    );
                }

                if (
                    $requestedMinor
                    > $invoiceRemainingMinor
                ) {
                    throw new PaymentAllocationConflictException(
                        'Jumlah alokasi melebihi sisa tagihan.'
                    );
                }

                $paymentSettings =
                    $this->paymentSettingService
                        ->get();

                if (
                    ! $paymentSettings
                        ->partial_payment_enabled
                    && $requestedMinor
                        !== $invoiceRemainingMinor
                ) {
                    throw new PaymentAllocationConflictException(
                        'Pembayaran sebagian tidak diizinkan untuk usaha ini.'
                    );
                }

                $allocation =
                    PaymentAllocation::query()
                        ->create([
                            'tenant_id' =>
                                $this->tenantContext
                                    ->tenantId(),

                            'business_id' =>
                                $this->businessContext
                                    ->businessId(),

                            'payment_id' =>
                                $payment->id,

                            'invoice_id' =>
                                $invoice->id,

                            'allocated_amount' =>
                                $this->minorToDecimal(
                                    $requestedMinor
                                ),
                        ]);

                /*
                 * Hitung ulang dari ledger SETELAH insert.
                 */
                $newPaidMinor =
                    $this->minorUnits(
                        PaymentAllocation::query()
                            ->join(
                                'payments',
                                function ($join): void {
                                    $join
                                        ->on(
                                            'payments.id',
                                            '=',
                                            'payment_allocations.payment_id'
                                        )
                                        ->on(
                                            'payments.tenant_id',
                                            '=',
                                            'payment_allocations.tenant_id'
                                        )
                                        ->on(
                                            'payments.business_id',
                                            '=',
                                            'payment_allocations.business_id'
                                        );
                                }
                            )
                            ->where(
                                'payment_allocations.tenant_id',
                                $this->tenantContext
                                    ->tenantId()
                            )
                            ->where(
                                'payment_allocations.business_id',
                                $this->businessContext
                                    ->businessId()
                            )
                            ->where(
                                'payment_allocations.invoice_id',
                                $invoice->id
                            )
                            ->where(
                                'payments.status',
                                'VERIFIED'
                            )
                            ->sum(
                                'payment_allocations.allocated_amount'
                            )
                    );

                $outstandingMinor =
                    $invoiceTotalMinor
                    - $newPaidMinor;

                if ($outstandingMinor < 0) {
                    throw new PaymentAllocationConflictException(
                        'Perhitungan outstanding tagihan tidak valid.'
                    );
                }

                $this->assertReceivableInvariant(
                    $invoiceTotalMinor,
                    $newPaidMinor,
                    $outstandingMinor
                );

                $fromState =
                    $invoice->status;

                $toState =
                    $outstandingMinor === 0
                        ? 'PAID'
                        : 'PARTIALLY_PAID';

                $invoice->fill([
                    'paid_amount' =>
                        $this->minorToDecimal(
                            $newPaidMinor
                        ),

                    'outstanding_amount' =>
                        $this->minorToDecimal(
                            $outstandingMinor
                        ),

                    'status' =>
                        $toState,
                ]);

                $invoice->save();

                if ($fromState !== $toState) {
                    InvoiceStatusHistory::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $this->tenantContext
                                    ->tenantId(),

                            'business_id' =>
                                $invoice->business_id,

                            'invoice_id' =>
                                $invoice->id,

                            'from_state' =>
                                $fromState,

                            'to_state' =>
                                $toState,

                            'actor_user_id' =>
                                $this->tenantContext
                                    ->userId(),

                            'reason' =>
                                null,

                            'source' =>
                                'PAYMENT',

                            'context' => [
                                'payment_id' =>
                                    $payment->id,

                                'allocation_id' =>
                                    $allocation->id,
                            ],

                            'occurred_at' =>
                                now(),
                        ]);
                }

                $paymentAllocatedMinor =
                    $allocatedPaymentMinor
                    + $requestedMinor;

                $paymentUnallocatedMinor =
                    $paymentAmountMinor
                    - $paymentAllocatedMinor;

                $payment->load([
                    'customer',
                    'evidenceFile',
                ]);

                $invoice->load([
                    'customer',
                    'statusHistory',
                ]);

                return [
                    'allocation' =>
                        $allocation,

                    'payment' =>
                        $payment,

                    'invoice' =>
                        $invoice,

                    'payment_allocated_amount' =>
                        $this->minorToDecimal(
                            $paymentAllocatedMinor
                        ),

                    'payment_unallocated_amount' =>
                        $this->minorToDecimal(
                            $paymentUnallocatedMinor
                        ),
                ];
            },
            3
        );
    }

    public function replaceEvidence(
        string $paymentId,
        UploadedFile $uploadedFile
    ): Payment {
        $newFile =
            $this->fileService
                ->storePaymentProof(
                    $uploadedFile
                );

        $oldFile = null;

        try {
            DB::transaction(
                function () use (
                    $paymentId,
                    $newFile,
                    &$oldFile
                ): void {
                    $payment =
                        $this->baseQuery()
                            ->where(
                                'id',
                                $paymentId
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $canAttachToVerified =
                        $payment->status === 'VERIFIED'
                        && $payment->evidence_file_id
                            === null;

                    if (
                        $payment->status !== 'PENDING'
                        && ! $canAttachToVerified
                    ) {
                        throw new PaymentEvidenceLockedException(
                            $payment->status
                        );
                    }

                    if (
                        $payment
                            ->evidence_file_id
                        !== null
                    ) {
                        $oldFile =
                            FileAsset::query()
                                ->where(
                                    'tenant_id',
                                    $this
                                        ->tenantContext
                                        ->tenantId()
                                )
                                ->where(
                                    'id',
                                    $payment
                                        ->evidence_file_id
                                )
                                ->where(
                                    'purpose',
                                    'PAYMENT_PROOF'
                                )
                                ->first();
                    }

                    $payment
                        ->evidence_file_id =
                        $newFile->id;

                    $payment->save();
                }
            );
        } catch (\Throwable $exception) {
            $this->fileService
                ->deleteObject(
                    $newFile
                );

            $newFile->delete();

            throw $exception;
        }

        if ($oldFile !== null) {
            $this->fileService
                ->deleteObject(
                    $oldFile
                );

            $oldFile->delete();
        }

        return $this->findOrFail(
            $paymentId
        );
    }

    public function evidenceFile(
        string $paymentId
    ): ?FileAsset {
        $payment =
            $this->baseQuery()
                ->where(
                    'id',
                    $paymentId
                )
                ->firstOrFail();

        if (
            $payment->evidence_file_id
            === null
        ) {
            return null;
        }

        return FileAsset::query()
            ->where(
                'tenant_id',
                $this->tenantContext
                    ->tenantId()
            )
            ->where(
                'id',
                $payment
                    ->evidence_file_id
            )
            ->where(
                'purpose',
                'PAYMENT_PROOF'
            )
            ->first();
    }

    public function removeEvidence(
        string $paymentId
    ): Payment {
        $oldFile = null;

        DB::transaction(
            function () use (
                $paymentId,
                &$oldFile
            ): void {
                $payment =
                    $this->baseQuery()
                        ->where(
                            'id',
                            $paymentId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $payment->status !== 'PENDING'
                ) {
                    throw new PaymentEvidenceLockedException(
                        $payment->status
                    );
                }

                if (
                    $payment
                        ->evidence_file_id
                    !== null
                ) {
                    $oldFile =
                        FileAsset::query()
                            ->where(
                                'tenant_id',
                                $this
                                    ->tenantContext
                                    ->tenantId()
                            )
                            ->where(
                                'id',
                                $payment
                                    ->evidence_file_id
                            )
                            ->where(
                                'purpose',
                                'PAYMENT_PROOF'
                            )
                            ->first();
                }

                $payment
                    ->evidence_file_id =
                    null;

                $payment->save();
            }
        );

        if ($oldFile !== null) {
            $this->fileService
                ->deleteObject(
                    $oldFile
                );

            $oldFile->delete();
        }

        return $this->findOrFail(
            $paymentId
        );
    }

    private function assertReceivableInvariant(
        int $totalMinor,
        int $paidMinor,
        int $outstandingMinor
    ): void {
        if (
            $totalMinor < 0
            || $paidMinor < 0
            || $outstandingMinor < 0
            || $paidMinor > $totalMinor
            || $outstandingMinor > $totalMinor
            || $paidMinor + $outstandingMinor !== $totalMinor
        ) {
            throw new \LogicException(
                'Invariant piutang tagihan tidak valid.'
            );
        }
    }

    private function minorUnits(
        mixed $value
    ): int {
        if (is_int($value)) {
            return $value * 100;
        }

        if (is_float($value)) {
            $value = number_format(
                $value,
                2,
                '.',
                ''
            );
        }

        $value = trim(
            (string) $value
        );

        if (
            ! preg_match(
                '/^\d+(?:\.\d{1,2})?$/',
                $value
            )
        ) {
            throw new \LogicException(
                'Nilai decimal tidak valid.'
            );
        }

        [$whole, $fraction] =
            array_pad(
                explode(
                    '.',
                    $value,
                    2
                ),
                2,
                '0'
            );

        $fraction =
            str_pad(
                $fraction,
                2,
                '0'
            );

        return ((int) $whole * 100)
            + (int) substr(
                $fraction,
                0,
                2
            );
    }

    private function minorToDecimal(
        int $minor
    ): string {
        return sprintf(
            '%d.%02d',
            intdiv(
                $minor,
                100
            ),
            $minor % 100
        );
    }

    private function baseQuery(): Builder
    {
        return Payment::query()
            ->where(
                'tenant_id',
                $this->tenantContext
                    ->tenantId()
            )
            ->where(
                'business_id',
                $this->businessContext
                    ->businessId()
            );
    }
}
