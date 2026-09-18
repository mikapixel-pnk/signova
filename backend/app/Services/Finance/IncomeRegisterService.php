<?php

namespace App\Services\Finance;

use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class IncomeRegisterService
{
    private const GROUP_SOURCE_TYPES = [
        'CUSTOMER_PAYMENT' => [
            'PAYMENT',
        ],

        'MANUAL' => [
            'MANUAL_INCOME',
        ],

        /*
         * Future contracts.
         *
         * Belum ada writer untuk source type ini.
         * Read model disiapkan agar POS/Omnichannel
         * tidak perlu membongkar contract frontend.
         */
        'POS' => [
            'POS_PAYMENT',
        ],

        'MARKETPLACE' => [
            'CHANNEL_SETTLEMENT',
        ],
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext
    ) {
    }

    public function paginate(
        ?string $search = null,
        ?string $group = null,
        ?string $from = null,
        ?string $to = null,
        ?string $cashAccountId = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = $this->baseQuery();

        if ($search !== null && $search !== '') {
            $this->applySearch(
                $query,
                $search
            );
        }

        if ($group !== null) {
            $this->applyGroup(
                $query,
                $group
            );
        }

        if ($from !== null) {
            $query->whereDate(
                'ct.occurred_at',
                '>=',
                $from
            );
        }

        if ($to !== null) {
            $query->whereDate(
                'ct.occurred_at',
                '<=',
                $to
            );
        }

        if ($cashAccountId !== null) {
            $query->where(
                'ct.cash_account_id',
                $cashAccountId
            );
        }

        $paginator =
            $query
                ->orderByDesc(
                    'ct.occurred_at'
                )
                ->orderByDesc(
                    'ct.id'
                )
                ->paginate(
                    $perPage
                );

        $paginator->setCollection(
            $paginator
                ->getCollection()
                ->map(
                    fn ($row): array =>
                        $this->present(
                            $row
                        )
                )
        );

        return $paginator;
    }

    private function baseQuery(): Builder
    {
        $tenantId =
            $this->tenantContext
                ->tenantId();

        $businessId =
            $this->businessContext
                ->businessId();

        return DB::table(
            'cash_transactions as ct'
        )
            ->join(
                'cash_accounts as ca',
                function ($join): void {
                    $join
                        ->on(
                            'ca.id',
                            '=',
                            'ct.cash_account_id'
                        )
                        ->on(
                            'ca.tenant_id',
                            '=',
                            'ct.tenant_id'
                        )
                        ->on(
                            'ca.business_id',
                            '=',
                            'ct.business_id'
                        );
                }
            )
            ->leftJoin(
                'payments as payment',
                function ($join): void {
                    $join
                        ->on(
                            'payment.id',
                            '=',
                            'ct.source_id'
                        )
                        ->on(
                            'payment.tenant_id',
                            '=',
                            'ct.tenant_id'
                        )
                        ->on(
                            'payment.business_id',
                            '=',
                            'ct.business_id'
                        )
                        ->where(
                            'ct.source_type',
                            '=',
                            'PAYMENT'
                        );
                }
            )
            ->leftJoin(
                'customers as customer',
                function ($join): void {
                    $join
                        ->on(
                            'customer.id',
                            '=',
                            'payment.customer_id'
                        )
                        ->on(
                            'customer.tenant_id',
                            '=',
                            'payment.tenant_id'
                        )
                        ->on(
                            'customer.business_id',
                            '=',
                            'payment.business_id'
                        );
                }
            )
            ->leftJoin(
                'invoices as invoice',
                function ($join): void {
                    $join
                        ->on(
                            'invoice.id',
                            '=',
                            'payment.intended_invoice_id'
                        )
                        ->on(
                            'invoice.tenant_id',
                            '=',
                            'payment.tenant_id'
                        )
                        ->on(
                            'invoice.business_id',
                            '=',
                            'payment.business_id'
                        );
                }
            )
            ->leftJoin(
                'incomes as income',
                function ($join): void {
                    $join
                        ->on(
                            'income.id',
                            '=',
                            'ct.source_id'
                        )
                        ->on(
                            'income.tenant_id',
                            '=',
                            'ct.tenant_id'
                        )
                        ->on(
                            'income.business_id',
                            '=',
                            'ct.business_id'
                        )
                        ->where(
                            'ct.source_type',
                            '=',
                            'MANUAL_INCOME'
                        );
                }
            )
            ->where(
                'ct.tenant_id',
                $tenantId
            )
            ->where(
                'ct.business_id',
                $businessId
            )
            ->where(
                'ct.direction',
                'IN'
            )
            ->select([
                'ct.id',
                'ct.source_type',
                'ct.source_id',
                'ct.amount',
                'ct.currency',
                'ct.occurred_at',
                'ct.reference',
                'ct.description',

                'ca.id as cash_account_id',
                'ca.name as cash_account_name',
                'ca.type as cash_account_type',
                'ca.bank_name as cash_account_bank_name',

                'payment.id as payment_id',
                'payment.status as payment_status',

                'customer.id as customer_id',
                'customer.name as customer_name',

                'invoice.id as invoice_id',
                'invoice.invoice_number as invoice_number',

                'income.id as income_id',
                'income.category as income_category',
                'income.status as income_status',
            ])
            ->selectRaw(
                '(
                    SELECT COUNT(*)
                    FROM cash_transactions AS reversal
                    WHERE reversal.tenant_id = ct.tenant_id
                      AND reversal.business_id = ct.business_id
                      AND reversal.reversal_of_transaction_id = ct.id
                ) AS reversal_count'
            );
    }

    private function applySearch(
        Builder $query,
        string $search
    ): void {
        $like =
            '%' . trim($search) . '%';

        $query->where(
            function (
                Builder $query
            ) use ($like): void {
                $query
                    ->where(
                        'ct.reference',
                        'ILIKE',
                        $like
                    )
                    ->orWhere(
                        'ct.description',
                        'ILIKE',
                        $like
                    )
                    ->orWhere(
                        'customer.name',
                        'ILIKE',
                        $like
                    )
                    ->orWhere(
                        'invoice.invoice_number',
                        'ILIKE',
                        $like
                    )
                    ->orWhere(
                        'income.category',
                        'ILIKE',
                        $like
                    )
                    ->orWhere(
                        'ca.name',
                        'ILIKE',
                        $like
                    )
                    ->orWhere(
                        'ca.bank_name',
                        'ILIKE',
                        $like
                    );
            }
        );
    }

    private function applyGroup(
        Builder $query,
        string $group
    ): void {
        if (
            isset(
                self::GROUP_SOURCE_TYPES[
                    $group
                ]
            )
        ) {
            $query->whereIn(
                'ct.source_type',
                self::GROUP_SOURCE_TYPES[
                    $group
                ]
            );

            return;
        }

        if ($group !== 'OTHER') {
            return;
        }

        $knownSourceTypes = [];

        foreach (
            self::GROUP_SOURCE_TYPES
            as $sourceTypes
        ) {
            foreach (
                $sourceTypes
                as $sourceType
            ) {
                $knownSourceTypes[] =
                    $sourceType;
            }
        }

        $query->whereNotIn(
            'ct.source_type',
            array_values(
                array_unique(
                    $knownSourceTypes
                )
            )
        );
    }

    private function present(
        object $row
    ): array {
        $group =
            $this->sourceGroup(
                $row->source_type
            );

        return [
            'id' =>
                $row->id,

            'occurred_at' =>
                $row->occurred_at
                    ? Carbon::parse(
                        $row->occurred_at
                    )->toISOString()
                    : null,

            'amount' =>
                number_format(
                    (float) $row->amount,
                    2,
                    '.',
                    ''
                ),

            'currency' =>
                $row->currency,

            'source' => [
                'group' =>
                    $group,

                'type' =>
                    $row->source_type,

                'label' =>
                    $this->sourceLabel(
                        $row
                    ),

                'category' =>
                    $row->income_category,

                'automatic' =>
                    $row->source_type
                    !== 'MANUAL_INCOME',

                /*
                 * Channel/provider belum disimpan
                 * pada CashTransaction saat ini.
                 * Future CHANNEL_SETTLEMENT dapat
                 * mengisinya dari aggregate sumber
                 * tanpa mengubah contract response.
                 */
                'channel' =>
                    null,

                'status' =>
                    $this->sourceStatus(
                        $row
                    ),
            ],

            'cash_account' => [
                'id' =>
                    $row->cash_account_id,

                'name' =>
                    $row->cash_account_name,

                'type' =>
                    $row->cash_account_type,

                'bank_name' =>
                    $row
                        ->cash_account_bank_name,
            ],

            'party' =>
                $row->customer_id
                    ? [
                        'id' =>
                            $row->customer_id,

                        'name' =>
                            $row->customer_name,
                    ]
                    : null,

            'document' =>
                $row->invoice_id
                    ? [
                        'type' =>
                            'INVOICE',

                        'id' =>
                            $row->invoice_id,

                        'number' =>
                            $row->invoice_number,
                    ]
                    : null,

            'reference' =>
                $row->reference,

            'description' =>
                $row->description,

            /*
             * Original cash receipt tetap
             * dipertahankan untuk audit.
             * Bila ada compensating transaction,
             * frontend menandainya sebagai dibalik.
             */
            'reversed' =>
                (int) $row
                    ->reversal_count > 0,
        ];
    }

    private function sourceGroup(
        string $sourceType
    ): string {
        return match (
            $sourceType
        ) {
            'PAYMENT' =>
                'CUSTOMER_PAYMENT',

            'MANUAL_INCOME' =>
                'MANUAL',

            'POS_PAYMENT' =>
                'POS',

            'CHANNEL_SETTLEMENT' =>
                'MARKETPLACE',

            default =>
                'OTHER',
        };
    }

    private function sourceLabel(
        object $row
    ): string {
        return match (
            $row->source_type
        ) {
            'PAYMENT' =>
                $row->invoice_id
                    ? 'Pembayaran Tagihan'
                    : 'Pembayaran Pelanggan',

            'MANUAL_INCOME' =>
                'Pemasukan Lainnya',

            'POS_PAYMENT' =>
                'Penjualan POS',

            'CHANNEL_SETTLEMENT' =>
                'Settlement Marketplace',

            default =>
                'Pemasukan',
        };
    }

    private function sourceStatus(
        object $row
    ): ?string {
        return match (
            $row->source_type
        ) {
            'PAYMENT' =>
                $row->payment_status,

            'MANUAL_INCOME' =>
                $row->income_status,

            default =>
                null,
        };
    }
}
