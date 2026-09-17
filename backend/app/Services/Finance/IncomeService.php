<?php

namespace App\Services\Finance;

use App\Exceptions\Finance\EntityHasActivityException;
use App\Exceptions\Finance\IncomeStateConflictException;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Income;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IncomeService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
    ) {
    }

    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?string $cashAccountId = null,
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
                                ->orWhere(
                                    'reference',
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
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $incomeId
    ): Income {
        return $this->baseQuery()
            ->where('id', $incomeId)
            ->firstOrFail();
    }

    public function create(
        array $data
    ): Income {
        return DB::transaction(
            function () use ($data): Income {
                $cashAccountId =
                    $data['cash_account_id']
                    ?? null;

                if ($cashAccountId !== null) {
                    $this->findCashAccountOrFail(
                        $cashAccountId
                    );
                }

                $income =
                    Income::query()->create([
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

                        'occurred_at' =>
                            $data['occurred_at'],

                        'category' =>
                            $data['category']
                            ?? null,

                        'description' =>
                            $data['description'],

                        'reference' =>
                            $data['reference']
                            ?? null,

                        'status' =>
                            'DRAFT',

                        'created_by_user_id' =>
                            $this->tenantContext
                                ->userId(),
                    ]);

                return $this->withRelations(
                    $income->id
                );
            },
            3
        );
    }

    public function update(
        string $incomeId,
        array $data
    ): Income {
        return DB::transaction(
            function () use (
                $incomeId,
                $data
            ): Income {
                $income =
                    $this->locked($incomeId);

                if ($income->status !== 'DRAFT') {
                    throw new IncomeStateConflictException(
                        'Hanya pemasukan DRAFT yang dapat diubah.'
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

                $income->fill($data);
                $income->currency = 'IDR';

                $income->save();

                return $this->withRelations(
                    $income->id
                );
            },
            3
        );
    }

    public function delete(
        string $incomeId
    ): void {
        DB::transaction(
            function () use ($incomeId): void {
                $income =
                    $this->locked($incomeId);

                if ($income->status !== 'DRAFT') {
                    throw new IncomeStateConflictException(
                        'Pemasukan yang sudah diposting atau dibatalkan tidak dapat dihapus.'
                    );
                }

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
                            $income->id
                        )
                        ->whereIn(
                            'source_type',
                            [
                                'MANUAL_INCOME',
                                'MANUAL_INCOME_VOID',
                            ]
                        )
                        ->exists();

                if ($hasActivity) {
                    throw new EntityHasActivityException(
                        'Pemasukan'
                    );
                }

                $income->delete();
            },
            3
        );
    }

    public function post(
        string $incomeId
    ): Income {
        return DB::transaction(
            function () use ($incomeId): Income {
                $income =
                    $this->locked($incomeId);

                if ($income->status !== 'DRAFT') {
                    throw new IncomeStateConflictException(
                        'Hanya pemasukan DRAFT yang dapat diposting.'
                    );
                }

                if ($income->cash_account_id === null) {
                    throw new IncomeStateConflictException(
                        'Pilih rekening Kas & Bank sebelum memposting pemasukan.'
                    );
                }

                $account =
                    $this->findCashAccountOrFail(
                        $income->cash_account_id,
                        true
                    );

                if ($account->status !== 'ACTIVE') {
                    throw new IncomeStateConflictException(
                        'Pemasukan hanya dapat diposting ke rekening ACTIVE.'
                    );
                }

                $postedAt = now();

                $income->status =
                    'POSTED';

                $income->posted_by_user_id =
                    $this->tenantContext
                        ->userId();

                $income->posted_at =
                    $postedAt;

                $income->save();

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
                        'IN',

                    'amount' =>
                        $income->amount,

                    'currency' =>
                        $income->currency,

                    'occurred_at' =>
                        $income->occurred_at,

                    'source_type' =>
                        'MANUAL_INCOME',

                    'source_id' =>
                        $income->id,

                    'reference' =>
                        $income->reference,

                    'description' =>
                        $income->description,

                    'reversal_of_transaction_id' =>
                        null,

                    'created_by_user_id' =>
                        $this->tenantContext
                            ->userId(),
                ]);

                return $this->withRelations(
                    $income->id
                );
            },
            3
        );
    }

    public function void(
        string $incomeId,
        string $reason
    ): Income {
        return DB::transaction(
            function () use (
                $incomeId,
                $reason
            ): Income {
                $income =
                    $this->locked($incomeId);

                if ($income->status !== 'POSTED') {
                    throw new IncomeStateConflictException(
                        'Hanya pemasukan POSTED yang dapat dibatalkan.'
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
                            'MANUAL_INCOME'
                        )
                        ->where(
                            'business_id',
                            $this->businessContext
                                ->businessId()
                        )
                        ->where(
                            'source_id',
                            $income->id
                        )
                        ->lockForUpdate()
                        ->first();

                if ($original === null) {
                    throw new IncomeStateConflictException(
                        'Ledger pemasukan tidak ditemukan.'
                    );
                }

                if (
                    $original->direction !== 'IN'
                    || $original->cash_account_id
                        !== $income->cash_account_id
                    || bccomp(
                        (string) $original->amount,
                        (string) $income->amount,
                        2
                    ) !== 0
                ) {
                    throw new IncomeStateConflictException(
                        'Ledger pemasukan tidak konsisten.'
                    );
                }

                $voidedAt = now();

                $income->status =
                    'VOID';

                $income->voided_by_user_id =
                    $this->tenantContext
                        ->userId();

                $income->voided_at =
                    $voidedAt;

                $income->void_reason =
                    $reason;

                $income->save();

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
                        'OUT',

                    'amount' =>
                        $original->amount,

                    'currency' =>
                        $original->currency,

                    'occurred_at' =>
                        $voidedAt,

                    'source_type' =>
                        'MANUAL_INCOME_VOID',

                    'source_id' =>
                        $income->id,

                    'reference' =>
                        $income->reference,

                    'description' =>
                        'Pembatalan pemasukan: '
                        . $reason,

                    'reversal_of_transaction_id' =>
                        $original->id,

                    'created_by_user_id' =>
                        $this->tenantContext
                            ->userId(),
                ]);

                return $this->withRelations(
                    $income->id
                );
            },
            3
        );
    }

    private function locked(
        string $incomeId
    ): Income {
        return $this->tenantQuery()
            ->where(
                'id',
                $incomeId
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
        return Income::query()
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
            ->with('cashAccount');
    }

    private function withRelations(
        string $incomeId
    ): Income {
        return $this->findOrFail(
            $incomeId
        );
    }
}
