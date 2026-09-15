<?php

namespace App\Services\Quotation;

use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\QuotationAction;
use App\Models\QuotationItem;
use App\Models\QuotationStatusHistory;
use App\Models\QuotationVersion;
use App\Exceptions\Quotation\InvalidQuotationTransitionException;
use App\Exceptions\Quotation\QuotationNotEditableException;
use App\Models\Unit;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class QuotationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly QuotationPricingCalculator $pricingCalculator,
    ) {
    }

    public function paginate(
        ?string $search = null,
        ?string $status = null,
        ?string $customerId = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->baseQuery()
            ->with([
                'customer',
                'currentVersion.items',
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
                                    'quotation_number',
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
                ) => $query->where(
                    'status',
                    $status
                )
            )
            ->when(
                $customerId,
                fn (
                    Builder $query,
                    string $customerId
                ) => $query->where(
                    'customer_id',
                    $customerId
                )
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $quotationId
    ): Quotation {
        return $this->baseQuery()
            ->with([
                'customer',
                'currentVersion.items',
            ])
            ->where('id', $quotationId)
            ->firstOrFail();
    }

    public function createDraft(
        array $header,
        array $version,
        array $items
    ): Quotation {
        return DB::transaction(function () use (
            $header,
            $version,
            $items
        ): Quotation {
            $tenantId =
                $this->tenantContext->tenantId();

            $userId =
                $this->tenantContext->userId();

            $this->findCustomerOrFail(
                $header['customer_id']
            );

            if ($items === []) {
                throw new InvalidArgumentException(
                    'Penawaran harus memiliki minimal satu item.'
                );
            }

            $quotationNumber =
                $this->nextQuotationNumber(
                    $tenantId
                );

            $quotation = Quotation::query()->create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'quotation_number' =>
                    $quotationNumber,
                'customer_id' =>
                    $header['customer_id'],
                'current_version_id' => null,
                'status' => 'DRAFT',
                'valid_until' =>
                    $header['valid_until'] ?? null,
                'owner_user_id' => $userId,
                'source' =>
                    $header['source'] ?? 'MANUAL',
            ]);

            $quotationVersion =
                $this->createVersionRecord(
                    $quotation,
                    1,
                    $version,
                    $items
                );

            $quotation->current_version_id =
                $quotationVersion->id;

            $quotation->save();

            QuotationStatusHistory::query()->create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'quotation_id' =>
                    $quotation->id,
                'from_state' => null,
                'to_state' => 'DRAFT',
                'actor_user_id' => $userId,
                'reason' => null,
                'source' => 'USER',
                'context' => [
                    'revision_no' => 1,
                ],
                'occurred_at' => now(),
            ]);

            return $this->findOrFail(
                $quotation->id
            );
        });
    }

    public function updateDraftHeader(
        string $quotationId,
        array $attributes
    ): Quotation {
        return DB::transaction(function () use (
            $quotationId,
            $attributes
        ): Quotation {
            $quotation = $this->baseQuery()
                ->where('id', $quotationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quotation->status !== 'DRAFT') {
                throw new QuotationNotEditableException(
                    'Penawaran hanya dapat diubah saat masih berstatus Draf.'
                );
            }

            if (
                array_key_exists(
                    'customer_id',
                    $attributes
                )
            ) {
                $this->findCustomerOrFail(
                    $attributes['customer_id']
                );

                $quotation->customer_id =
                    $attributes['customer_id'];
            }

            if (
                array_key_exists(
                    'valid_until',
                    $attributes
                )
            ) {
                $quotation->valid_until =
                    $attributes['valid_until'];
            }

            $quotation->save();

            return $this->findOrFail(
                $quotation->id
            );
        });
    }

    public function createRevision(
        string $quotationId,
        array $version,
        array $items
    ): Quotation {
        return DB::transaction(function () use (
            $quotationId,
            $version,
            $items
        ): Quotation {
            if ($items === []) {
                throw new InvalidArgumentException(
                    'Versi penawaran harus memiliki minimal satu item.'
                );
            }

            $quotation = $this->baseQuery()
                ->where('id', $quotationId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! in_array(
                    $quotation->status,
                    [
                        'DRAFT',
                        'REJECTED',
                    ],
                    true
                )
            ) {
                throw new QuotationNotEditableException(
                    'Revisi hanya dapat dibuat dari Penawaran Draf atau yang memerlukan revisi.'
                );
            }

            $previousStatus =
                $quotation->status;

            $latestRevision = QuotationVersion::query()
                ->where(
                    'tenant_id',
                    $this->tenantContext->tenantId()
                )
                ->where(
                    'quotation_id',
                    $quotation->id
                )
                ->orderByDesc('revision_no')
                ->value('revision_no');

            $nextRevision =
                ((int) $latestRevision) + 1;

            $newVersion =
                $this->createVersionRecord(
                    $quotation,
                    $nextRevision,
                    $version,
                    $items
                );

            $this->assertVersionBelongsToQuotation(
                $quotation,
                $newVersion
            );

            $quotation->current_version_id =
                $newVersion->id;

            if (
                $previousStatus ===
                'REJECTED'
            ) {
                $quotation->status =
                    'DRAFT';

                $quotation->rejected_at =
                    null;
            }

            $quotation->save();

            if (
                $previousStatus ===
                'REJECTED'
            ) {
                QuotationStatusHistory::query()
                    ->create([
                        'id' =>
                            (string) Str::ulid(),

                        'tenant_id' =>
                            $this->tenantContext
                                ->tenantId(),

                        'quotation_id' =>
                            $quotation->id,

                        'from_state' =>
                            'REJECTED',

                        'to_state' =>
                            'DRAFT',

                        'actor_user_id' =>
                            $this->tenantContext
                                ->userId(),

                        'reason' =>
                            'Revisi penawaran dibuat.',

                        'source' =>
                            'USER',

                        'context' => [
                            'revision_no' =>
                                $nextRevision,

                            'quotation_version_id' =>
                                $newVersion->id,
                        ],

                        'occurred_at' =>
                            now(),
                    ]);
            }

            return $this->findOrFail(
                $quotation->id
            );
        });
    }

    public function send(
        string $quotationId
    ): Quotation {
        return $this->transition(
            $quotationId,
            'SENT',
            ['DRAFT'],
            null,
            [
                'sent_at' => now(),
            ]
        );
    }

    public function recordManualDecision(
        string $quotationId,
        string $decision,
        string $method,
        ?string $reason = null,
        ?string $note = null,
        ?string $decidedAt = null
    ): Quotation {
        return DB::transaction(
            function () use (
                $quotationId,
                $decision,
                $method,
                $reason,
                $note,
                $decidedAt
            ): Quotation {
                $quotation =
                    $this->baseQuery()
                        ->where(
                            'id',
                            $quotationId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    ! in_array(
                        $quotation->status,
                        [
                            'SENT',
                            'VIEWED',
                        ],
                        true
                    )
                ) {
                    throw new InvalidQuotationTransitionException(
                        $quotation->status,
                        $decision === 'APPROVE'
                            ? 'APPROVED'
                            : 'REJECTED'
                    );
                }

                if (
                    $quotation->current_version_id
                    === null
                ) {
                    throw new RuntimeException(
                        'Penawaran tidak memiliki versi aktif.'
                    );
                }

                $fromState =
                    $quotation->status;

                $toState =
                    $decision === 'APPROVE'
                        ? 'APPROVED'
                        : 'REJECTED';

                $occurredAt =
                    $decidedAt
                        ? \Illuminate\Support\Carbon::parse(
                            $decidedAt
                        )
                        : now();

                $quotation->status =
                    $toState;

                if ($toState === 'APPROVED') {
                    $quotation->approved_at =
                        $occurredAt;

                    $quotation->rejected_at =
                        null;
                } else {
                    $quotation->rejected_at =
                        $occurredAt;

                    $quotation->approved_at =
                        null;
                }

                $quotation->save();

                QuotationAction::query()->create([
                    'id' =>
                        (string) Str::ulid(),

                    'tenant_id' =>
                        $this->tenantContext
                            ->tenantId(),

                    'quotation_id' =>
                        $quotation->id,

                    'quotation_version_id' =>
                        $quotation
                            ->current_version_id,

                    'public_link_id' =>
                        null,

                    'action' =>
                        $decision,

                    'actor_type' =>
                        'USER',

                    'actor_user_id' =>
                        $this->tenantContext
                            ->userId(),

                    'note' =>
                        $note
                        ?? $reason,

                    'context' => [
                        'decision_source' =>
                            'MANUAL',

                        'method' =>
                            $method,

                        'reason' =>
                            $reason,

                        'decided_at' =>
                            $occurredAt
                                ->toISOString(),
                    ],

                    'occurred_at' =>
                        $occurredAt,
                ]);

                QuotationStatusHistory::query()
                    ->create([
                        'id' =>
                            (string) Str::ulid(),

                        'tenant_id' =>
                            $this->tenantContext
                                ->tenantId(),

                        'quotation_id' =>
                            $quotation->id,

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
                            'USER',

                        'context' => [
                            'decision_source' =>
                                'MANUAL',

                            'method' =>
                                $method,

                            'quotation_version_id' =>
                                $quotation
                                    ->current_version_id,
                        ],

                        'occurred_at' =>
                            $occurredAt,
                    ]);

                return $this->findOrFail(
                    $quotation->id
                );
            }
        );
    }


    public function cancel(
        string $quotationId,
        string $reason
    ): Quotation {
        return $this->transition(
            $quotationId,
            'CANCELLED',
            [
                'DRAFT',
                'SENT',
                'VIEWED',
            ],
            $reason,
            [
                'cancelled_at' => now(),
            ]
        );
    }

    public function assertVersionBelongsToQuotation(
        Quotation $quotation,
        QuotationVersion $version
    ): void {
        $tenantId =
            $this->tenantContext->tenantId();

        if (
            $quotation->tenant_id !== $tenantId
            || $version->tenant_id !== $tenantId
            || $version->quotation_id !== $quotation->id
        ) {
            throw new RuntimeException(
                'Quotation version does not belong to quotation.'
            );
        }
    }

    private function transition(
        string $quotationId,
        string $toState,
        array $allowedFromStates,
        ?string $reason = null,
        array $attributes = []
    ): Quotation {
        return DB::transaction(function () use (
            $quotationId,
            $toState,
            $allowedFromStates,
            $reason,
            $attributes
        ): Quotation {
            $quotation = $this->baseQuery()
                ->where('id', $quotationId)
                ->lockForUpdate()
                ->firstOrFail();

            $fromState = $quotation->status;

            if (
                ! in_array(
                    $fromState,
                    $allowedFromStates,
                    true
                )
            ) {
                throw new InvalidQuotationTransitionException(
                    $fromState,
                    $toState
                );
            }

            $quotation->fill(
                array_merge(
                    $attributes,
                    [
                        'status' => $toState,
                    ]
                )
            );

            $quotation->save();

            QuotationStatusHistory::query()->create([
                'id' => (string) Str::ulid(),
                'tenant_id' =>
                    $this->tenantContext->tenantId(),
                'quotation_id' =>
                    $quotation->id,
                'from_state' =>
                    $fromState,
                'to_state' =>
                    $toState,
                'actor_user_id' =>
                    $this->tenantContext->userId(),
                'reason' =>
                    $reason,
                'source' =>
                    'USER',
                'context' => [],
                'occurred_at' =>
                    now(),
            ]);

            return $this->findOrFail(
                $quotation->id
            );
        });
    }

    private function nextQuotationNumber(
        string $tenantId
    ): string {
        $prefix =
            strtoupper(
                trim(
                    (string) config(
                        'signova_documents.quotation.prefix',
                        'PEN'
                    )
                )
            );

        if ($prefix === '') {
            $prefix = 'PEN';
        }

        $period =
            now()->format(
                (string) config(
                    'signova_documents.quotation.period_format',
                    'ym'
                )
            );

        $sequenceDigits =
            (int) config(
                'signova_documents.quotation.sequence_digits',
                6
            );

        $base =
            $prefix
            . '-'
            . $period;

        DB::select(
            'SELECT pg_advisory_xact_lock('
            . 'hashtextextended(?, 0)'
            . ')',
            [
                'signova:quotation-number:'
                . $tenantId
                . ':'
                . $base,
            ]
        );

        $pattern =
            '^'
            . preg_quote(
                $base,
                '/'
            )
            . '-[0-9]{'
            . $sequenceDigits
            . '}$';

        $latestNumber =
            Quotation::query()
                ->where(
                    'tenant_id',
                    $tenantId
                )
                ->where(
                    'quotation_number',
                    'LIKE',
                    $base . '-%'
                )
                ->whereRaw(
                    'quotation_number ~ ?',
                    [$pattern]
                )
                ->orderByRaw(
                    'CAST(RIGHT(quotation_number, ?) AS INTEGER) DESC',
                    [$sequenceDigits]
                )
                ->value(
                    'quotation_number'
                );

        $sequence = 1;

        if (
            is_string($latestNumber)
            && preg_match(
                '/-([0-9]{'
                . $sequenceDigits
                . '})$/',
                $latestNumber,
                $matches
            ) === 1
        ) {
            $sequence =
                ((int) $matches[1])
                + 1;
        }

        return sprintf(
            '%s-%0'
            . $sequenceDigits
            . 'd',
            $base,
            $sequence
        );
    }

    private function createVersionRecord(
        Quotation $quotation,
        int $revisionNo,
        array $version,
        array $items
    ): QuotationVersion {
        $tenantId =
            $this->tenantContext->tenantId();

        $userId =
            $this->tenantContext->userId();

        $resolvedItems = [];

        foreach (
            array_values($items)
            as $index => $item
        ) {
            $snapshot =
                $this->resolveItemSnapshot(
                    $item
                );

            $resolvedItems[] = array_merge(
                $item,
                [
                    'catalog_item_id' =>
                        $snapshot['catalog_item_id'],

                    'unit_id' =>
                        $snapshot['unit_id'],

                    'item_type' =>
                        $snapshot['item_type'],

                    'code' =>
                        $snapshot['code'],

                    'name' =>
                        $snapshot['name'],

                    'description' =>
                        $snapshot['description'],

                    'unit_code' =>
                        $snapshot['unit_code'],

                    'unit_name' =>
                        $snapshot['unit_name'],

                    'unit_symbol' =>
                        $snapshot['unit_symbol'],

                    'pricing_method' =>
                        $snapshot['pricing_method'],

                    'pricing_config' =>
                        array_merge(
                            $snapshot['pricing_config']
                                ?? [],
                            is_array(
                                $item['pricing_config']
                                ?? null
                            )
                                ? $item['pricing_config']
                                : []
                        ),

                    'unit_price' =>
                        $item['unit_price']
                        ?? $snapshot['base_price'],

                    'sort_order' =>
                        $item['sort_order']
                        ?? $index,
                ]
            );
        }

        $pricing =
            $this->pricingCalculator
                ->calculate(
                    $resolvedItems
                );

        $quotationVersion =
            QuotationVersion::query()->create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'quotation_id' =>
                    $quotation->id,
                'revision_no' => $revisionNo,

                'subtotal' =>
                    $pricing['subtotal'],

                'discount_total' =>
                    $pricing['discount_total'],

                'tax_total' =>
                    $pricing['tax_total'],

                'total' =>
                    $pricing['total'],

                'currency' =>
                    $version['currency']
                    ?? 'IDR',

                'terms' =>
                    $version['terms']
                    ?? null,

                'notes' =>
                    $version['notes']
                    ?? null,

                'created_by_user_id' => $userId,
            ]);

        foreach (
            $pricing['items']
            as $index => $item
        ) {
            QuotationItem::query()->create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,

                'quotation_version_id' =>
                    $quotationVersion->id,

                'catalog_item_id' =>
                    $item['catalog_item_id']
                    ?? null,

                'unit_id' =>
                    $item['unit_id']
                    ?? null,

                'item_type' =>
                    $item['item_type']
                    ?? null,

                'code' =>
                    $item['code']
                    ?? null,

                'name' =>
                    $item['name'],

                'description' =>
                    $item['description']
                    ?? null,

                'quantity' =>
                    $item['quantity'],

                'unit_code' =>
                    $item['unit_code']
                    ?? null,

                'unit_name' =>
                    $item['unit_name']
                    ?? null,

                'unit_symbol' =>
                    $item['unit_symbol']
                    ?? null,

                'pricing_method' =>
                    $item['pricing_method'],

                'pricing_config' =>
                    $item['pricing_config'],

                'unit_price' =>
                    $item['unit_price'],

                'discount_amount' =>
                    $item['discount_amount'],

                'tax_amount' =>
                    $item['tax_amount'],

                'amount' =>
                    $item['amount'],

                'sort_order' =>
                    $item['sort_order']
                    ?? $index,
            ]);
        }

        return $quotationVersion;
    }

    private function resolveItemSnapshot(
        array $item
    ): array {
        $catalogItem = null;
        $unit = null;

        if (
            isset($item['catalog_item_id'])
            && $item['catalog_item_id'] !== null
        ) {
            $catalogItem = CatalogItem::query()
                ->where(
                    'tenant_id',
                    $this->tenantContext->tenantId()
                )
                ->where(
                    'id',
                    $item['catalog_item_id']
                )
                ->firstOrFail();
        }

        $unitId =
            $item['unit_id']
            ?? $catalogItem?->unit_id;

        if ($unitId !== null) {
            $unit = Unit::query()
                ->where(
                    'tenant_id',
                    $this->tenantContext->tenantId()
                )
                ->where('id', $unitId)
                ->firstOrFail();
        }

        if (
            $catalogItem === null
            && empty($item['name'])
        ) {
            throw new InvalidArgumentException(
                'Item manual harus memiliki nama.'
            );
        }

        return [
            'catalog_item_id' =>
                $catalogItem?->id,
            'unit_id' =>
                $unit?->id,

            'item_type' =>
                $item['item_type']
                ?? $catalogItem?->type,

            'code' =>
                $item['code']
                ?? $catalogItem?->code,

            'name' =>
                $item['name']
                ?? $catalogItem?->name,

            'description' =>
                $item['description']
                ?? $catalogItem?->description,

            'unit_code' =>
                $item['unit_code']
                ?? $unit?->code,

            'unit_name' =>
                $item['unit_name']
                ?? $unit?->name,

            'unit_symbol' =>
                $item['unit_symbol']
                ?? $unit?->symbol,

            'pricing_method' =>
                $item['pricing_method']
                ?? $catalogItem?->pricing_method
                ?? 'MANUAL',

            'pricing_config' =>
                $catalogItem?->pricing_config,

            'base_price' =>
                $catalogItem?->base_price
                ?? 0,
        ];
    }

    private function findCustomerOrFail(
        string $customerId
    ): Customer {
        return Customer::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            )
            ->where('id', $customerId)
            ->firstOrFail();
    }

    private function baseQuery(): Builder
    {
        return Quotation::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            );
    }
}
