<?php

namespace App\Services\Finance;

use App\Exceptions\Finance\CashAccountStateConflictException;
use App\Exceptions\Finance\EntityHasActivityException;
use App\Models\CashAccount;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashAccountService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?string $type = null,
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
                                    'name',
                                    'ILIKE',
                                    '%' . $search . '%'
                                )
                                ->orWhere(
                                    'bank_name',
                                    'ILIKE',
                                    '%' . $search . '%'
                                )
                                ->orWhere(
                                    'account_number',
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
                ) => $query->where(
                    'status',
                    $status
                )
            )
            ->when(
                $type,
                fn (
                    Builder $query,
                    string $type
                ) => $query->where(
                    'type',
                    $type
                )
            )
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $cashAccountId
    ): CashAccount {
        return $this->baseQuery()
            ->where(
                'id',
                $cashAccountId
            )
            ->firstOrFail();
    }

    public function create(
        array $data
    ): CashAccount {
        return DB::transaction(
            function () use ($data): CashAccount {
                DB::table('tenants')
                    ->where(
                        'id',
                        $this->tenantContext
                            ->tenantId()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $hasAccount =
                    $this->baseQuery()
                        ->exists();

                $type =
                    $data['type'];

                $account =
                    CashAccount::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $this->tenantContext
                                    ->tenantId(),

                            'name' =>
                                $data['name'],

                            'type' =>
                                $type,

                            'bank_name' =>
                                $type === 'BANK'
                                    ? $data['bank_name']
                                    : null,

                            'account_number' =>
                                $type === 'BANK'
                                    ? ($data[
                                        'account_number'
                                    ] ?? null)
                                    : null,

                            'account_name' =>
                                $type === 'BANK'
                                    ? $data['account_name']
                                    : null,

                            'currency' =>
                                'IDR',

                            'status' =>
                                'ACTIVE',

                            'is_default' =>
                                ! $hasAccount,

                            'created_by_user_id' =>
                                $this->tenantContext
                                    ->userId(),
                        ]);

                return $this->withBalance(
                    $account->id
                );
            },
            3
        );
    }

    public function update(
        string $cashAccountId,
        array $data
    ): CashAccount {
        return DB::transaction(
            function () use (
                $cashAccountId,
                $data
            ): CashAccount {
                $account =
                    $this->tenantQuery()
                        ->where(
                            'id',
                            $cashAccountId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $finalType =
                    $data['type']
                    ?? $account->type;

                if (
                    $finalType === 'BANK'
                    && (
                        array_key_exists(
                            'bank_name',
                            $data
                        )
                            ? trim(
                                (string) $data[
                                    'bank_name'
                                ]
                            ) === ''
                            : trim(
                                (string) $account
                                    ->bank_name
                            ) === ''
                    )
                ) {
                    throw new CashAccountStateConflictException(
                        'Nama bank wajib diisi untuk rekening BANK.'
                    );
                }

                if (
                    $finalType === 'BANK'
                    && (
                        array_key_exists(
                            'account_name',
                            $data
                        )
                            ? trim(
                                (string) $data[
                                    'account_name'
                                ]
                            ) === ''
                            : trim(
                                (string) $account
                                    ->account_name
                            ) === ''
                    )
                ) {
                    throw new CashAccountStateConflictException(
                        'Nama pemilik rekening wajib diisi untuk rekening BANK.'
                    );
                }

                $account->fill([
                    'name' =>
                        $data['name']
                        ?? $account->name,

                    'type' =>
                        $finalType,

                    'bank_name' =>
                        $finalType === 'BANK'
                            ? (
                                $data['bank_name']
                                ?? $account->bank_name
                            )
                            : null,

                    'account_number' =>
                        $finalType === 'BANK'
                            ? (
                                array_key_exists(
                                    'account_number',
                                    $data
                                )
                                    ? $data[
                                        'account_number'
                                    ]
                                    : $account
                                        ->account_number
                            )
                            : null,

                    'account_name' =>
                        $finalType === 'BANK'
                            ? (
                                $data['account_name']
                                ?? $account->account_name
                            )
                            : null,
                ]);

                $account->save();

                return $this->withBalance(
                    $account->id
                );
            },
            3
        );
    }

    public function activate(
        string $cashAccountId
    ): CashAccount {
        return DB::transaction(
            function () use (
                $cashAccountId
            ): CashAccount {
                $account =
                    $this->locked(
                        $cashAccountId
                    );

                $account->status =
                    'ACTIVE';

                $account->save();

                return $this->withBalance(
                    $account->id
                );
            },
            3
        );
    }

    public function deactivate(
        string $cashAccountId
    ): CashAccount {
        return DB::transaction(
            function () use (
                $cashAccountId
            ): CashAccount {
                $account =
                    $this->locked(
                        $cashAccountId
                    );

                if ($account->is_default) {
                    throw new CashAccountStateConflictException(
                        'Rekening default tidak dapat dinonaktifkan. Jadikan rekening lain sebagai default terlebih dahulu.'
                    );
                }

                $account->status =
                    'INACTIVE';

                $account->save();

                return $this->withBalance(
                    $account->id
                );
            },
            3
        );
    }

    public function setDefault(
        string $cashAccountId
    ): CashAccount {
        return DB::transaction(
            function () use (
                $cashAccountId
            ): CashAccount {
                $account =
                    $this->locked(
                        $cashAccountId
                    );

                if (
                    $account->status
                    !== 'ACTIVE'
                ) {
                    throw new CashAccountStateConflictException(
                        'Hanya rekening ACTIVE yang dapat dijadikan default.'
                    );
                }

                $this->baseQuery()
                    ->where(
                        'is_default',
                        true
                    )
                    ->where(
                        'id',
                        '!=',
                        $account->id
                    )
                    ->update([
                        'is_default' =>
                            false,

                        'updated_at' =>
                            now(),
                    ]);

                if (! $account->is_default) {
                    $account->is_default =
                        true;

                    $account->save();
                }

                return $this->withBalance(
                    $account->id
                );
            },
            3
        );
    }

    public function delete(
        string $cashAccountId
    ): void {
        DB::transaction(
            function () use (
                $cashAccountId
            ): void {
                $account =
                    $this->locked(
                        $cashAccountId
                    );

                if ($account->is_default) {
                    throw new CashAccountStateConflictException(
                        'Rekening default tidak dapat dihapus. Jadikan rekening lain sebagai default terlebih dahulu.'
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
                            'cash_account_id',
                            $account->id
                        )
                        ->exists()
                    || DB::table(
                        'payments'
                    )
                        ->where(
                            'tenant_id',
                            $this->tenantContext
                                ->tenantId()
                        )
                        ->where(
                            'cash_account_id',
                            $account->id
                        )
                        ->exists()
                    || DB::table(
                        'expenses'
                    )
                        ->where(
                            'tenant_id',
                            $this->tenantContext
                                ->tenantId()
                        )
                        ->where(
                            'cash_account_id',
                            $account->id
                        )
                        ->exists()
                    || DB::table(
                        'incomes'
                    )
                        ->where(
                            'tenant_id',
                            $this->tenantContext
                                ->tenantId()
                        )
                        ->where(
                            'cash_account_id',
                            $account->id
                        )
                        ->exists();

                if ($hasActivity) {
                    throw new EntityHasActivityException(
                        'Rekening Kas & Bank'
                    );
                }

                $account->delete();
            },
            3
        );
    }

    private function locked(
        string $cashAccountId
    ): CashAccount {
        return $this->tenantQuery()
            ->where(
                'id',
                $cashAccountId
            )
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function tenantQuery(): Builder
    {
        return CashAccount::query()
            ->where(
                'tenant_id',
                $this->tenantContext
                    ->tenantId()
            );
    }

    private function baseQuery(): Builder
    {
        return $this->tenantQuery()
            ->select(
                'cash_accounts.*'
            )
            ->selectRaw(
                "COALESCE(
                    (
                        SELECT SUM(
                            CASE
                                WHEN ct.direction = 'IN'
                                    THEN ct.amount
                                ELSE -ct.amount
                            END
                        )
                        FROM cash_transactions ct
                        WHERE
                            ct.tenant_id =
                                cash_accounts.tenant_id
                            AND ct.cash_account_id =
                                cash_accounts.id
                    ),
                    0
                )::numeric(18,2) AS balance"
            );
    }

    private function withBalance(
        string $cashAccountId
    ): CashAccount {
        return $this->findOrFail(
            $cashAccountId
        );
    }
}
