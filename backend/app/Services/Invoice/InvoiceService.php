<?php

namespace App\Services\Invoice;

use App\Exceptions\Invoice\InvalidInvoiceTransitionException;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceStatusHistory;
use App\Models\Unit;
use App\Services\Document\DocumentNumberService;
use App\Services\Invoice\Document\InvoiceBrandingSnapshotService;
use App\Services\Invoice\Document\InvoiceTemplateSnapshotService;
use App\Services\Quotation\QuotationPricingCalculator;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
        private readonly DocumentNumberService $documentNumberService,
        private readonly QuotationPricingCalculator $pricingCalculator,
        private readonly InvoiceAdjustmentCalculator $adjustmentCalculator,
        private readonly InvoiceTemplateSnapshotService $templateSnapshotService,
        private readonly InvoiceBrandingSnapshotService $brandingSnapshotService
    ) {
    }

    public function createDraft(
        string $customerId,
        array $items,
        ?string $dueAt = null,
        ?string $notes = null,
        ?array $adjustments = null
    ): Invoice {
        return DB::transaction(
            function () use (
                $customerId,
                $items,
                $dueAt,
                $notes,
                $adjustments
            ): Invoice {
                $tenantId =
                    $this->tenantContext
                        ->tenantId();

                $businessId =
                    $this->businessContext
                        ->businessId();

                $userId =
                    $this->tenantContext
                        ->userId();

                $customer =
                    Customer::query()
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
                            $customerId
                        )
                        ->firstOrFail();

                $resolvedItems = [];

                foreach (
                    array_values($items)
                    as $index => $item
                ) {
                    $snapshot =
                        $this->resolveItemSnapshot(
                            $item
                        );

                    $resolvedItems[] =
                        array_merge(
                            $item,
                            [
                                'catalog_item_id' =>
                                    $snapshot[
                                        'catalog_item_id'
                                    ],

                                'item_type' =>
                                    $snapshot[
                                        'item_type'
                                    ],

                                'code' =>
                                    $snapshot[
                                        'code'
                                    ],

                                'name' =>
                                    $snapshot[
                                        'name'
                                    ],

                                'description' =>
                                    $snapshot[
                                        'description'
                                    ],

                                'unit_code' =>
                                    $snapshot[
                                        'unit_code'
                                    ],

                                'unit_name' =>
                                    $snapshot[
                                        'unit_name'
                                    ],

                                'unit_symbol' =>
                                    $snapshot[
                                        'unit_symbol'
                                    ],

                                'pricing_method' =>
                                    $snapshot[
                                        'pricing_method'
                                    ],

                                'pricing_config' =>
                                    array_merge(
                                        $snapshot[
                                            'pricing_config'
                                        ] ?? [],
                                        is_array(
                                            $item[
                                                'pricing_config'
                                            ] ?? null
                                        )
                                            ? $item[
                                                'pricing_config'
                                            ]
                                            : []
                                    ),

                                'unit_price' =>
                                    $item[
                                        'unit_price'
                                    ]
                                    ?? $snapshot[
                                        'base_price'
                                    ],

                                'sort_order' =>
                                    $item[
                                        'sort_order'
                                    ]
                                    ?? $index,
                            ]
                        );
                }

                if (
                    $adjustments !== null
                ) {
                    $resolvedItems =
                        array_map(
                            fn (array $item): array =>
                                array_merge(
                                    $item,
                                    [
                                        'tax_rate' =>
                                            0,
                                    ]
                                ),
                            $resolvedItems
                        );
                }

                $pricing =
                    $this->pricingCalculator
                        ->calculate(
                            $resolvedItems
                        );

                if (
                    $adjustments !== null
                ) {
                    $pricing =
                        array_merge(
                            $pricing,
                            $this->adjustmentCalculator
                                ->calculate(
                                    $pricing,
                                    $adjustments
                                )
                        );
                }

                $invoiceId =
                    (string) Str::ulid();

                $invoiceNumber =
                    $this->documentNumberService
                        ->nextInvoiceNumber();

                $invoice =
                    Invoice::query()
                        ->create([
                            'id' =>
                                $invoiceId,

                            'tenant_id' =>
                                $tenantId,

                            'business_id' =>
                                $businessId,

                            'invoice_number' =>
                                $invoiceNumber,

                            'customer_id' =>
                                $customer->id,

                            'project_id' =>
                                null,

                            'source_quotation_id' =>
                                null,

                            'source_quotation_version_id' =>
                                null,

                            'status' =>
                                'DRAFT',

                            'issued_at' =>
                                null,

                            'due_at' =>
                                $dueAt,

                            'currency' =>
                                'IDR',

                            'subtotal' =>
                                $pricing[
                                    'subtotal'
                                ],

                            'discount_total' =>
                                $pricing[
                                    'discount_total'
                                ],

                            'item_discount_total' =>
                                $adjustments !== null
                                    ? $pricing[
                                        'item_discount_total'
                                    ]
                                    : null,

                            'global_discount_type' =>
                                $adjustments !== null
                                    ? $pricing[
                                        'global_discount_type'
                                    ]
                                    : null,

                            'global_discount_value' =>
                                $adjustments !== null
                                    ? $pricing[
                                        'global_discount_value'
                                    ]
                                    : null,

                            'global_discount_amount' =>
                                $adjustments !== null
                                    ? $pricing[
                                        'global_discount_amount'
                                    ]
                                    : null,

                            'tax_enabled' =>
                                $adjustments !== null
                                    ? $pricing[
                                        'tax_enabled'
                                    ]
                                    : null,

                            'tax_rate' =>
                                $adjustments !== null
                                    ? $pricing[
                                        'tax_rate'
                                    ]
                                    : null,

                            'tax_total' =>
                                $pricing[
                                    'tax_total'
                                ],

                            'total' =>
                                $pricing[
                                    'total'
                                ],

                            'paid_amount' =>
                                '0.00',

                            'outstanding_amount' =>
                                '0.00',

                            'notes' =>
                                $notes,

                            'created_by_user_id' =>
                                $userId,
                        ]);

                foreach (
                    $pricing['items']
                    as $index => $item
                ) {
                    InvoiceItem::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $tenantId,

                            'business_id' =>
                                $businessId,

                            'invoice_id' =>
                                $invoiceId,

                            'source_quotation_item_id' =>
                                null,

                            'catalog_item_id' =>
                                $item[
                                    'catalog_item_id'
                                ]
                                ?? null,

                            'item_type' =>
                                $item[
                                    'item_type'
                                ]
                                ?? null,

                            'code' =>
                                $item['code']
                                ?? null,

                            'name' =>
                                $item['name'],

                            'description' =>
                                $item[
                                    'description'
                                ]
                                ?? null,

                            'quantity' =>
                                $item[
                                    'quantity'
                                ],

                            'unit_code' =>
                                $item[
                                    'unit_code'
                                ]
                                ?? null,

                            'unit_name' =>
                                $item[
                                    'unit_name'
                                ]
                                ?? null,

                            'unit_symbol' =>
                                $item[
                                    'unit_symbol'
                                ]
                                ?? null,

                            'pricing_method' =>
                                $item[
                                    'pricing_method'
                                ]
                                ?? null,

                            'pricing_config' =>
                                $item[
                                    'pricing_config'
                                ]
                                ?? null,

                            'pricing_quantity' =>
                                $item[
                                    'pricing_quantity'
                                ]
                                ?? null,

                            'unit_price' =>
                                $item[
                                    'unit_price'
                                ],

                            'discount_amount' =>
                                $item[
                                    'discount_amount'
                                ],

                            'tax_amount' =>
                                $item[
                                    'tax_amount'
                                ],

                            'amount' =>
                                $item[
                                    'amount'
                                ],

                            'sort_order' =>
                                $item[
                                    'sort_order'
                                ]
                                ?? $index,
                        ]);
                }

                InvoiceStatusHistory::query()
                    ->create([
                        'id' =>
                            (string) Str::ulid(),

                        'tenant_id' =>
                            $tenantId,

                        'business_id' =>
                            $businessId,

                        'invoice_id' =>
                            $invoiceId,

                        'from_state' =>
                            null,

                        'to_state' =>
                            'DRAFT',

                        'actor_user_id' =>
                            $userId,

                        'reason' =>
                            null,

                        'source' =>
                            'USER',

                        'context' => [
                            'creation_type' =>
                                'MANUAL',
                        ],

                        'occurred_at' =>
                            now(),
                    ]);

                return $this->findOrFail(
                    $invoiceId
                );
            }
        );
    }

    public function updateDraft(
        string $invoiceId,
        array $data
    ): Invoice {
        return DB::transaction(
            function () use (
                $invoiceId,
                $data
            ): Invoice {
                $tenantId =
                    $this->tenantContext
                        ->tenantId();

                $businessId =
                    $this->businessContext
                        ->businessId();

                $invoice =
                    $this->baseQuery()
                        ->where(
                            'id',
                            $invoiceId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if ($invoice->status !== 'DRAFT') {
                    throw new InvalidInvoiceTransitionException(
                        $invoice->status,
                        'DRAFT'
                    );
                }

                /*
                 * Endpoint ini khusus editor Tagihan Manual.
                 * Invoice hasil konversi Penawaran menjaga provenance
                 * melalui flow Penawaran.
                 */
                if (
                    $invoice->source_quotation_id !== null
                    || $invoice->source_quotation_version_id !== null
                ) {
                    throw new InvalidInvoiceTransitionException(
                        'QUOTATION_DRAFT',
                        'MANUAL_EDIT'
                    );
                }

                if (
                    array_key_exists(
                        'customer_id',
                        $data
                    )
                ) {
                    $customer =
                        Customer::query()
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
                                $data['customer_id']
                            )
                            ->firstOrFail();

                    $invoice->customer_id =
                        $customer->id;
                }

                if (
                    array_key_exists(
                        'due_at',
                        $data
                    )
                ) {
                    $invoice->due_at =
                        $data['due_at'];
                }

                if (
                    array_key_exists(
                        'notes',
                        $data
                    )
                ) {
                    $invoice->notes =
                        $data['notes'];
                }

                if (
                    array_key_exists(
                        'items',
                        $data
                    )
                ) {
                    $resolvedItems = [];

                    foreach (
                        array_values(
                            $data['items']
                        )
                        as $index => $item
                    ) {
                        $snapshot =
                            $this->resolveItemSnapshot(
                                $item
                            );

                        $resolvedItems[] =
                            array_merge(
                                $item,
                                [
                                    'catalog_item_id' =>
                                        $snapshot[
                                            'catalog_item_id'
                                        ],

                                    'item_type' =>
                                        $snapshot[
                                            'item_type'
                                        ],

                                    'code' =>
                                        $snapshot[
                                            'code'
                                        ],

                                    'name' =>
                                        $snapshot[
                                            'name'
                                        ],

                                    'description' =>
                                        $snapshot[
                                            'description'
                                        ],

                                    'unit_code' =>
                                        $snapshot[
                                            'unit_code'
                                        ],

                                    'unit_name' =>
                                        $snapshot[
                                            'unit_name'
                                        ],

                                    'unit_symbol' =>
                                        $snapshot[
                                            'unit_symbol'
                                        ],

                                    'pricing_method' =>
                                        $snapshot[
                                            'pricing_method'
                                        ],

                                    'pricing_config' =>
                                        array_merge(
                                            $snapshot[
                                                'pricing_config'
                                            ] ?? [],
                                            is_array(
                                                $item[
                                                    'pricing_config'
                                                ] ?? null
                                            )
                                                ? $item[
                                                    'pricing_config'
                                                ]
                                                : []
                                        ),

                                    'unit_price' =>
                                        $item[
                                            'unit_price'
                                        ]
                                        ?? $snapshot[
                                            'base_price'
                                        ],

                                    'sort_order' =>
                                        $item[
                                            'sort_order'
                                        ]
                                        ?? $index,
                                ]
                            );
                    }

                    $adjustments =
                        array_intersect_key(
                            $data,
                            array_flip([
                                'global_discount_type',
                                'global_discount_value',
                                'tax_enabled',
                                'tax_rate',
                            ])
                        );

                    $useAdjustments =
                        $adjustments !== [];

                    if ($useAdjustments) {
                        $resolvedItems =
                            array_map(
                                fn (array $item): array =>
                                    array_merge(
                                        $item,
                                        [
                                            'tax_rate' =>
                                                0,
                                        ]
                                    ),
                                $resolvedItems
                            );
                    }

                    $pricing =
                        $this->pricingCalculator
                            ->calculate(
                                $resolvedItems
                            );

                    if ($useAdjustments) {
                        $pricing =
                            array_merge(
                                $pricing,
                                $this->adjustmentCalculator
                                    ->calculate(
                                        $pricing,
                                        $adjustments
                                    )
                            );
                    }

                    InvoiceItem::query()
                        ->where(
                            'tenant_id',
                            $tenantId
                        )
                        ->where(
                            'business_id',
                            $businessId
                        )
                        ->where(
                            'invoice_id',
                            $invoice->id
                        )
                        ->delete();

                    foreach (
                        $pricing['items']
                        as $index => $item
                    ) {
                        InvoiceItem::query()
                            ->create([
                                'id' =>
                                    (string) Str::ulid(),

                                'tenant_id' =>
                                    $tenantId,

                                'business_id' =>
                                    $businessId,

                                'invoice_id' =>
                                    $invoice->id,

                                'source_quotation_item_id' =>
                                    null,

                                'catalog_item_id' =>
                                    $item[
                                        'catalog_item_id'
                                    ]
                                    ?? null,

                                'item_type' =>
                                    $item[
                                        'item_type'
                                    ]
                                    ?? null,

                                'code' =>
                                    $item['code']
                                    ?? null,

                                'name' =>
                                    $item['name'],

                                'description' =>
                                    $item[
                                        'description'
                                    ]
                                    ?? null,

                                'quantity' =>
                                    $item[
                                        'quantity'
                                    ],

                                'unit_code' =>
                                    $item[
                                        'unit_code'
                                    ]
                                    ?? null,

                                'unit_name' =>
                                    $item[
                                        'unit_name'
                                    ]
                                    ?? null,

                                'unit_symbol' =>
                                    $item[
                                        'unit_symbol'
                                    ]
                                    ?? null,

                                'pricing_method' =>
                                    $item[
                                        'pricing_method'
                                    ]
                                    ?? null,

                                'pricing_config' =>
                                    $item[
                                        'pricing_config'
                                    ]
                                    ?? null,

                                'pricing_quantity' =>
                                    $item[
                                        'pricing_quantity'
                                    ]
                                    ?? null,

                                'unit_price' =>
                                    $item[
                                        'unit_price'
                                    ],

                                'discount_amount' =>
                                    $item[
                                        'discount_amount'
                                    ],

                                'tax_amount' =>
                                    $item[
                                        'tax_amount'
                                    ],

                                'amount' =>
                                    $item[
                                        'amount'
                                    ],

                                'sort_order' =>
                                    $item[
                                        'sort_order'
                                    ]
                                    ?? $index,
                            ]);
                    }

                    $invoice->subtotal =
                        $pricing['subtotal'];

                    $invoice->discount_total =
                        $pricing[
                            'discount_total'
                        ];

                    if ($useAdjustments) {
                        $invoice->item_discount_total =
                            $pricing[
                                'item_discount_total'
                            ];

                        $invoice->global_discount_type =
                            $pricing[
                                'global_discount_type'
                            ];

                        $invoice->global_discount_value =
                            $pricing[
                                'global_discount_value'
                            ];

                        $invoice->global_discount_amount =
                            $pricing[
                                'global_discount_amount'
                            ];

                        $invoice->tax_enabled =
                            $pricing[
                                'tax_enabled'
                            ];

                        $invoice->tax_rate =
                            $pricing[
                                'tax_rate'
                            ];
                    }

                    $invoice->tax_total =
                        $pricing['tax_total'];

                    $invoice->total =
                        $pricing['total'];

                    /*
                     * DRAFT belum merupakan piutang.
                     */
                    $invoice->paid_amount =
                        '0.00';

                    $invoice->outstanding_amount =
                        '0.00';
                }

                $invoice->save();

                return $this->findOrFail(
                    $invoice->id
                );
            }
        );
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
                                    'invoice_number',
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
        string $invoiceId
    ): Invoice {
        return $this->baseQuery()
            ->with([
                'customer',
                'items',
                'statusHistory',
            ])
            ->where(
                'id',
                $invoiceId
            )
            ->firstOrFail();
    }

    public function issue(
        string $invoiceId
    ): Invoice {
        return $this->transition(
            $invoiceId,
            'ISSUED',
            ['DRAFT'],
            null,
            [
                'issued_at' => now(),
            ]
        );
    }

    public function void(
        string $invoiceId,
        string $reason
    ): Invoice {
        return $this->transition(
            $invoiceId,
            'VOID',
            [
                'DRAFT',
                'ISSUED',
            ],
            $reason
        );
    }

    private function transition(
        string $invoiceId,
        string $toState,
        array $allowedFromStates,
        ?string $reason = null,
        array $attributes = []
    ): Invoice {
        return DB::transaction(function () use (
            $invoiceId,
            $toState,
            $allowedFromStates,
            $reason,
            $attributes
        ): Invoice {
            $invoice = $this->baseQuery()
                ->where(
                    'id',
                    $invoiceId
                )
                ->lockForUpdate()
                ->firstOrFail();

            $fromState =
                $invoice->status;

            if (
                ! in_array(
                    $fromState,
                    $allowedFromStates,
                    true
                )
            ) {
                throw new InvalidInvoiceTransitionException(
                    $fromState,
                    $toState
                );
            }

            if ($toState === 'ISSUED') {
                $attributes['paid_amount'] =
                    '0.00';

                $attributes['outstanding_amount'] =
                    $invoice->total;

                $attributes =
                    array_merge(
                        $attributes,
                        $this->templateSnapshotService
                            ->current(),
                        $this->brandingSnapshotService
                            ->capture()
                    );
            }

            if ($toState === 'VOID') {
                $attributes['outstanding_amount'] =
                    '0.00';
            }

            $invoice->fill(
                array_merge(
                    $attributes,
                    [
                        'status' =>
                            $toState,
                    ]
                )
            );

            $invoice->save();

            InvoiceStatusHistory::query()
                ->create([
                    'id' =>
                        (string) Str::ulid(),

                    'tenant_id' =>
                        $this->tenantContext->tenantId(),

                    'business_id' =>
                        $this->businessContext->businessId(),

                    'invoice_id' =>
                        $invoice->id,

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
                $invoice->id
            );
        });
    }

    private function resolveItemSnapshot(
        array $item
    ): array {
        $catalogItem = null;
        $unit = null;

        if (
            isset(
                $item['catalog_item_id']
            )
            && $item[
                'catalog_item_id'
            ] !== null
        ) {
            $catalogItem =
                CatalogItem::query()
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
                        $item[
                            'catalog_item_id'
                        ]
                    )
                    ->firstOrFail();
        }

        $unitId =
            $item['unit_id']
            ?? $catalogItem?->unit_id;

        if ($unitId !== null) {
            $unit =
                Unit::query()
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
                        $unitId
                    )
                    ->firstOrFail();
        }

        return [
            'catalog_item_id' =>
                $catalogItem?->id,

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
                $item[
                    'pricing_method'
                ]
                ?? $catalogItem
                    ?->pricing_method
                ?? 'MANUAL',

            'pricing_config' =>
                $catalogItem
                    ?->pricing_config,

            'base_price' =>
                $catalogItem
                    ?->base_price
                ?? 0,
        ];
    }

    private function baseQuery(): Builder
    {
        return Invoice::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            )
            ->where(
                'business_id',
                $this->businessContext->businessId()
            );
    }
}
