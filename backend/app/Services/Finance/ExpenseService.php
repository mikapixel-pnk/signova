<?php

namespace App\Services\Finance;

use App\Exceptions\Finance\EntityHasActivityException;
use App\Exceptions\Finance\ExpenseStateConflictException;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Expense;
use App\Models\FileAsset;
use App\Services\File\FileService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpenseService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly FileService $fileService,
    ) {
    }

    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?string $cashAccountId = null,
        ?string $from = null,
        ?string $to = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->baseQuery()
            ->when(
                $search,
                fn (Builder $query, string $search) =>
                    $query->where(
                        fn (Builder $query) =>
                            $query
                                ->where(
                                    'description',
                                    'ILIKE',
                                    '%' . $search . '%'
                                )
                                ->orWhere(
                                    'category',
                                    'ILIKE',
                                    '%' . $search . '%'
                                )
                    )
            )
            ->when(
                $status,
                fn (Builder $query, string $status) =>
                    $query->where(
                        'status',
                        $status
                    )
            )
            ->when(
                $cashAccountId,
                fn (
                    Builder $query,
                    string $cashAccountId
                ) =>
                    $query->where(
                        'cash_account_id',
                        $cashAccountId
                    )
            )
            ->when(
                $from,
                fn (
                    Builder $query,
                    string $from
                ) =>
                    $query->whereDate(
                        'incurred_at',
                        '>=',
                        $from
                    )
            )
            ->when(
                $to,
                fn (
                    Builder $query,
                    string $to
                ) =>
                    $query->whereDate(
                        'incurred_at',
                        '<=',
                        $to
                    )
            )
            ->orderByDesc('incurred_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $expenseId
    ): Expense {
        return $this->baseQuery()
            ->where(
                'id',
                $expenseId
            )
            ->firstOrFail();
    }

    public function create(
        array $data
    ): Expense {
        return DB::transaction(
            function () use ($data): Expense {
                $cashAccountId =
                    $data['cash_account_id']
                    ?? null;

                if ($cashAccountId !== null) {
                    $this->findCashAccountOrFail(
                        $cashAccountId
                    );
                }

                $expense =
                    Expense::query()->create([
                        'id' =>
                            (string) Str::ulid(),

                        'tenant_id' =>
                            $this->tenantContext
                                ->tenantId(),

                        'business_id' =>
                            $this->businessContext
                                ->businessId(),

                        'cash_account_id' =>
                            $cashAccountId,

                        'amount' =>
                            $data['amount'],

                        'currency' =>
                            'IDR',

                        'incurred_at' =>
                            $data['incurred_at'],

                        'category' =>
                            $data['category']
                            ?? null,

                        'description' =>
                            $data['description'],

                        'status' =>
                            'DRAFT',

                        'created_by_user_id' =>
                            $this->tenantContext
                                ->userId(),
                    ]);

                return $this->withRelations(
                    $expense->id
                );
            },
            3
        );
    }

    public function record(
        array $data
    ): Expense {
        /*
         * Satu aksi pengguna harus atomik:
         * Expense dan CashTransaction harus sama-sama
         * tersimpan atau sama-sama rollback.
         *
         * create() dan post() memiliki transaction
         * masing-masing. Transaction luar ini tetap
         * menjadi commit boundary final.
         */
        return DB::transaction(
            function () use ($data): Expense {
                $expense =
                    $this->create(
                        $data
                    );

                return $this->post(
                    $expense->id
                );
            },
            3
        );
    }


    public function update(
        string $expenseId,
        array $data
    ): Expense {
        return DB::transaction(
            function () use (
                $expenseId,
                $data
            ): Expense {
                $expense =
                    $this->locked(
                        $expenseId
                    );

                if ($expense->status !== 'DRAFT') {
                    throw new ExpenseStateConflictException(
                        'Hanya pengeluaran DRAFT yang dapat diubah.'
                    );
                }

                if (
                    array_key_exists(
                        'cash_account_id',
                        $data
                    )
                    && $data[
                        'cash_account_id'
                    ] !== null
                ) {
                    $this->findCashAccountOrFail(
                        $data['cash_account_id']
                    );
                }

                $expense->fill($data);
                $expense->currency = 'IDR';
                $expense->save();

                return $this->withRelations(
                    $expense->id
                );
            },
            3
        );
    }

    public function delete(
        string $expenseId
    ): void {
        DB::transaction(
            function () use (
                $expenseId
            ): void {
                $expense =
                    $this->locked(
                        $expenseId
                    );

                $hasActivity =
                    DB::table(
                        'cash_transactions'
                    )
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
                            'source_id',
                            $expense->id
                        )
                        ->whereIn(
                            'source_type',
                            [
                                'EXPENSE',
                                'EXPENSE_VOID',
                            ]
                        )
                        ->exists();

                if ($hasActivity) {
                    throw new EntityHasActivityException(
                        'Pengeluaran'
                    );
                }

                if ($expense->status !== 'DRAFT') {
                    throw new ExpenseStateConflictException(
                        'Pengeluaran yang sudah diposting atau dibatalkan tidak dapat dihapus.'
                    );
                }

                $expense->delete();
            },
            3
        );
    }

    public function submit(
        string $expenseId
    ): Expense {
        return DB::transaction(
            function () use (
                $expenseId
            ): Expense {
                $expense =
                    $this->locked(
                        $expenseId
                    );

                if ($expense->status !== 'DRAFT') {
                    throw new ExpenseStateConflictException(
                        'Hanya pengeluaran DRAFT yang dapat diajukan.'
                    );
                }

                $this->activeCashAccountForExpense(
                    $expense
                );

                $fromStatus =
                    $expense->status;

                $submittedAt =
                    now();

                $expense->status =
                    'PENDING_APPROVAL';

                $expense->submitted_by_user_id =
                    $this->tenantContext
                        ->userId();

                $expense->submitted_at =
                    $submittedAt;

                $expense->save();

                $this->appendHistory(
                    $expense,
                    $fromStatus,
                    'PENDING_APPROVAL',
                    'SUBMITTED'
                );

                return $this->withRelations(
                    $expense->id
                );
            },
            3
        );
    }

    public function reject(
        string $expenseId,
        string $reason
    ): Expense {
        return DB::transaction(
            function () use (
                $expenseId,
                $reason
            ): Expense {
                $expense =
                    $this->locked(
                        $expenseId
                    );

                if (
                    $expense->status
                    !== 'PENDING_APPROVAL'
                ) {
                    throw new ExpenseStateConflictException(
                        'Hanya pengeluaran yang menunggu persetujuan yang dapat ditolak.'
                    );
                }

                $reason = trim($reason);

                if ($reason === '') {
                    throw new ExpenseStateConflictException(
                        'Alasan penolakan wajib diisi.'
                    );
                }

                $fromStatus =
                    $expense->status;

                $rejectedAt =
                    now();

                $expense->status =
                    'REJECTED';

                $expense->rejected_by_user_id =
                    $this->tenantContext
                        ->userId();

                $expense->rejected_at =
                    $rejectedAt;

                $expense->rejection_reason =
                    $reason;

                $expense->save();

                $this->appendHistory(
                    $expense,
                    $fromStatus,
                    'REJECTED',
                    'REJECTED',
                    $reason
                );

                return $this->withRelations(
                    $expense->id
                );
            },
            3
        );
    }

    public function revise(
        string $expenseId
    ): Expense {
        return DB::transaction(
            function () use (
                $expenseId
            ): Expense {
                $expense =
                    $this->locked(
                        $expenseId
                    );

                if ($expense->status !== 'REJECTED') {
                    throw new ExpenseStateConflictException(
                        'Hanya pengeluaran yang ditolak yang dapat diperbaiki.'
                    );
                }

                $fromStatus =
                    $expense->status;

                $expense->status =
                    'DRAFT';

                $expense->submitted_by_user_id =
                    null;

                $expense->submitted_at =
                    null;

                $expense->rejected_by_user_id =
                    null;

                $expense->rejected_at =
                    null;

                $expense->rejection_reason =
                    null;

                $expense->save();

                $this->appendHistory(
                    $expense,
                    $fromStatus,
                    'DRAFT',
                    'REVISED'
                );

                return $this->withRelations(
                    $expense->id
                );
            },
            3
        );
    }

    public function approve(
        string $expenseId
    ): Expense {
        return DB::transaction(
            function () use (
                $expenseId
            ): Expense {
                $expense =
                    $this->locked(
                        $expenseId
                    );

                if (
                    $expense->status
                    !== 'PENDING_APPROVAL'
                ) {
                    throw new ExpenseStateConflictException(
                        'Hanya pengeluaran yang menunggu persetujuan yang dapat disetujui.'
                    );
                }

                return $this->finalizePostedExpense(
                    $expense,
                    'PENDING_APPROVAL',
                    'APPROVED'
                );
            },
            3
        );
    }

    public function post(
        string $expenseId
    ): Expense {
        return DB::transaction(
            function () use (
                $expenseId
            ): Expense {
                $expense =
                    $this->locked(
                        $expenseId
                    );

                if ($expense->status !== 'DRAFT') {
                    throw new ExpenseStateConflictException(
                        'Hanya pengeluaran DRAFT yang dapat dicatat langsung.'
                    );
                }

                return $this->finalizePostedExpense(
                    $expense,
                    'DRAFT',
                    'DIRECT_POST'
                );
            },
            3
        );
    }

    public function void(
        string $expenseId,
        string $reason
    ): Expense {
        return DB::transaction(
            function () use (
                $expenseId,
                $reason
            ): Expense {
                $expense =
                    $this->locked(
                        $expenseId
                    );

                if (
                    $expense->status
                    !== 'POSTED'
                ) {
                    throw new ExpenseStateConflictException(
                        'Hanya pengeluaran POSTED yang dapat dibatalkan.'
                    );
                }

                $original =
                    CashTransaction::query()
                        ->where(
                            'tenant_id',
                            $this->tenantContext
                                ->tenantId()
                        )
                        ->where(
                            'source_type',
                            'EXPENSE'
                        )
                        ->where(
                            'business_id',
                            $this->businessContext
                                ->businessId()
                        )
                        ->where(
                            'source_id',
                            $expense->id
                        )
                        ->lockForUpdate()
                        ->first();

                if ($original === null) {
                    throw new ExpenseStateConflictException(
                        'Ledger pengeluaran tidak ditemukan.'
                    );
                }

                if (
                    $original->direction !== 'OUT'
                    || $original->cash_account_id
                        !== $expense->cash_account_id
                    || $original->currency
                        !== $expense->currency
                    || bccomp(
                        (string) $original->amount,
                        (string) $expense->amount,
                        2
                    ) !== 0
                ) {
                    throw new ExpenseStateConflictException(
                        'Ledger pengeluaran tidak konsisten.'
                    );
                }

                $voidedAt = now();

                $expense->status =
                    'VOID';

                $expense->voided_by_user_id =
                    $this->tenantContext
                        ->userId();

                $expense->voided_at =
                    $voidedAt;

                $expense->void_reason =
                    $reason;

                $expense->save();

                CashTransaction::query()->create([
                    'id' =>
                        (string) Str::ulid(),

                    'tenant_id' =>
                        $this->tenantContext
                            ->tenantId(),

                    'business_id' =>
                        $this->businessContext
                            ->businessId(),

                    'cash_account_id' =>
                        $original
                            ->cash_account_id,

                    'direction' =>
                        'IN',

                    'amount' =>
                        $original->amount,

                    'currency' =>
                        $original->currency,

                    'occurred_at' =>
                        $voidedAt,

                    'source_type' =>
                        'EXPENSE_VOID',

                    'source_id' =>
                        $expense->id,

                    'reference' =>
                        null,

                    'description' =>
                        'Pembatalan pengeluaran: '
                        . $reason,

                    'reversal_of_transaction_id' =>
                        $original->id,

                    'created_by_user_id' =>
                        $this->tenantContext
                            ->userId(),
                ]);

                $this->appendHistory(
                    $expense,
                    'POSTED',
                    'VOID',
                    'VOIDED',
                    $reason
                );

                return $this->withRelations(
                    $expense->id
                );
            },
            3
        );
    }

    public function replaceEvidence(
        string $expenseId,
        UploadedFile $uploadedFile
    ): Expense {
        $newFile =
            $this->fileService
                ->storeExpenseProof(
                    $uploadedFile
                );

        $oldFile = null;

        try {
            DB::transaction(
                function () use (
                    $expenseId,
                    $newFile,
                    &$oldFile
                ): void {
                    $expense =
                        $this->locked(
                            $expenseId
                        );

                    if ($expense->status !== 'DRAFT') {
                        throw new ExpenseStateConflictException(
                            'Bukti pengeluaran hanya dapat diubah saat status DRAFT.'
                        );
                    }

                    if (
                        $expense->evidence_file_id
                        !== null
                    ) {
                        $oldFile =
                            FileAsset::query()
                                ->where(
                                    'tenant_id',
                                    $this->tenantContext
                                        ->tenantId()
                                )
                                ->where(
                                    'id',
                                    $expense
                                        ->evidence_file_id
                                )
                                ->where(
                                    'purpose',
                                    'EXPENSE_PROOF'
                                )
                                ->first();
                    }

                    $expense->evidence_file_id =
                        $newFile->id;

                    $expense->save();
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

        return $this->withRelations(
            $expenseId
        );
    }

    public function evidenceFile(
        string $expenseId
    ): ?FileAsset {
        $expense =
            $this->findOrFail(
                $expenseId
            );

        if (
            $expense->evidence_file_id
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
                $expense->evidence_file_id
            )
            ->where(
                'purpose',
                'EXPENSE_PROOF'
            )
            ->first();
    }

    public function removeEvidence(
        string $expenseId
    ): Expense {
        $oldFile = null;

        DB::transaction(
            function () use (
                $expenseId,
                &$oldFile
            ): void {
                $expense =
                    $this->locked(
                        $expenseId
                    );

                if ($expense->status !== 'DRAFT') {
                    throw new ExpenseStateConflictException(
                        'Bukti pengeluaran hanya dapat dihapus saat status DRAFT.'
                    );
                }

                if (
                    $expense->evidence_file_id
                    !== null
                ) {
                    $oldFile =
                        FileAsset::query()
                            ->where(
                                'tenant_id',
                                $this->tenantContext
                                    ->tenantId()
                            )
                            ->where(
                                'id',
                                $expense
                                    ->evidence_file_id
                            )
                            ->where(
                                'purpose',
                                'EXPENSE_PROOF'
                            )
                            ->first();
                }

                $expense->evidence_file_id =
                    null;

                $expense->save();
            }
        );

        if ($oldFile !== null) {
            $this->fileService
                ->deleteObject(
                    $oldFile
                );

            $oldFile->delete();
        }

        return $this->withRelations(
            $expenseId
        );
    }

    private function finalizePostedExpense(
        Expense $expense,
        string $fromStatus,
        string $historyAction
    ): Expense {
        $account =
            $this->activeCashAccountForExpense(
                $expense
            );

        $postedAt =
            now();

        $actorUserId =
            $this->tenantContext
                ->userId();

        $expense->status =
            'POSTED';

        $expense->approved_by_user_id =
            $actorUserId;

        $expense->approved_at =
            $postedAt;

        $expense->posted_by_user_id =
            $actorUserId;

        $expense->posted_at =
            $postedAt;

        $expense->save();

        CashTransaction::query()->create([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $this->tenantContext
                    ->tenantId(),

            'business_id' =>
                $this->businessContext
                    ->businessId(),

            'cash_account_id' =>
                $account->id,

            'direction' =>
                'OUT',

            'amount' =>
                $expense->amount,

            'currency' =>
                $expense->currency,

            'occurred_at' =>
                $expense->incurred_at,

            'source_type' =>
                'EXPENSE',

            'source_id' =>
                $expense->id,

            'reference' =>
                null,

            'description' =>
                $expense->description,

            'reversal_of_transaction_id' =>
                null,

            'created_by_user_id' =>
                $actorUserId,
        ]);

        $this->appendHistory(
            $expense,
            $fromStatus,
            'POSTED',
            $historyAction
        );

        return $this->withRelations(
            $expense->id
        );
    }

    private function activeCashAccountForExpense(
        Expense $expense
    ): CashAccount {
        if ($expense->cash_account_id === null) {
            throw new ExpenseStateConflictException(
                'Pilih rekening Kas & Bank sebelum melanjutkan pengeluaran.'
            );
        }

        $account =
            $this->findCashAccountOrFail(
                $expense->cash_account_id,
                true
            );

        if ($account->status !== 'ACTIVE') {
            throw new ExpenseStateConflictException(
                'Pengeluaran hanya dapat menggunakan rekening ACTIVE.'
            );
        }

        return $account;
    }

    private function appendHistory(
        Expense $expense,
        string $fromStatus,
        string $toStatus,
        string $action,
        ?string $reason = null
    ): void {
        DB::table(
            'expense_status_history'
        )->insert([
            'id' =>
                (string) Str::ulid(),

            'tenant_id' =>
                $this->tenantContext
                    ->tenantId(),

            'business_id' =>
                $this->businessContext
                    ->businessId(),

            'expense_id' =>
                $expense->id,

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
        string $expenseId
    ): Expense {
        return $this->tenantQuery()
            ->where(
                'id',
                $expenseId
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function findCashAccountOrFail(
        string $cashAccountId,
        bool $lock = false
    ): CashAccount {
        $query =
            CashAccount::query()
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
                    $cashAccountId
                );

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function tenantQuery(): Builder
    {
        return Expense::query()
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

    private function baseQuery(): Builder
    {
        return $this->tenantQuery()
            ->with([
                'cashAccount',
                'evidenceFile',
            ]);
    }

    private function withRelations(
        string $expenseId
    ): Expense {
        return $this->findOrFail(
            $expenseId
        );
    }
}
