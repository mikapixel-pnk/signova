<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\InvalidPaymentTransitionException;
use App\Models\FileAsset;
use App\Models\Payment;
use App\Services\File\FileService;
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
                'evidenceFile',
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
                'evidenceFile',
            ])
            ->where(
                'id',
                $paymentId
            )
            ->firstOrFail();
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

                'customer_id' =>
                    $data['customer_id'],

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

                return $this->findOrFail(
                    $payment->id
                );
            }
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

    private function baseQuery(): Builder
    {
        return Payment::query()
            ->where(
                'tenant_id',
                $this->tenantContext
                    ->tenantId()
            );
    }
}
