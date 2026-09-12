<?php

namespace App\Services\Quotation;

use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationStatusHistory;
use App\Models\QuotationVersion;
use App\Models\Unit;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class QuotationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
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

            $quotation = Quotation::query()->create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'quotation_number' =>
                    $header['quotation_number'],
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

            $quotation->save();

            return $this->findOrFail(
                $quotation->id
            );
        });
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

        $quotationVersion =
            QuotationVersion::query()->create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'quotation_id' =>
                    $quotation->id,
                'revision_no' => $revisionNo,
                'subtotal' =>
                    $version['subtotal'] ?? 0,
                'discount_total' =>
                    $version['discount_total'] ?? 0,
                'tax_total' =>
                    $version['tax_total'] ?? 0,
                'total' =>
                    $version['total'] ?? 0,
                'currency' =>
                    $version['currency'] ?? 'IDR',
                'terms' =>
                    $version['terms'] ?? null,
                'notes' =>
                    $version['notes'] ?? null,
                'created_by_user_id' => $userId,
            ]);

        foreach (
            array_values($items)
            as $index => $item
        ) {
            $snapshot =
                $this->resolveItemSnapshot(
                    $item
                );

            QuotationItem::query()->create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'quotation_version_id' =>
                    $quotationVersion->id,
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
                'quantity' =>
                    $item['quantity'] ?? 1,
                'unit_code' =>
                    $snapshot['unit_code'],
                'unit_name' =>
                    $snapshot['unit_name'],
                'unit_symbol' =>
                    $snapshot['unit_symbol'],
                'pricing_method' =>
                    $snapshot['pricing_method'],
                'pricing_config' =>
                    $item['pricing_config']
                    ?? $snapshot['pricing_config'],
                'unit_price' =>
                    $item['unit_price'] ?? 0,
                'discount_amount' =>
                    $item['discount_amount'] ?? 0,
                'tax_amount' =>
                    $item['tax_amount'] ?? 0,
                'amount' =>
                    $item['amount'] ?? 0,
                'sort_order' =>
                    $item['sort_order'] ?? $index,
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
