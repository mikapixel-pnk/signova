<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceStatusHistory;
use App\Services\Quotation\QuotationService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class QuotationToInvoiceService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly QuotationService $quotationService,
    ) {
    }

    public function convert(
        string $quotationId,
        string $invoiceNumber,
        ?string $dueAt = null
    ): Invoice {
        $quotation =
            $this->quotationService->findOrFail(
                $quotationId
            );

        if ($quotation->status !== 'APPROVED') {
            throw new RuntimeException(
                'QUOTATION_NOT_APPROVED'
            );
        }

        $version = $quotation->currentVersion;

        if (! $version) {
            throw new RuntimeException(
                'QUOTATION_VERSION_MISSING'
            );
        }

        $tenantId =
            $this->tenantContext->tenantId();

        $existing = Invoice::query()
            ->where(
                'tenant_id',
                $tenantId
            )
            ->where(
                'source_quotation_version_id',
                $version->id
            )
            ->first();

        if ($existing) {
            return $existing->load([
                'items',
                'statusHistory',
            ]);
        }

        return DB::transaction(
            function () use (
                $quotation,
                $version,
                $tenantId,
                $invoiceNumber,
                $dueAt
            ): Invoice {
                $lockedQuotation = DB::table(
                    'quotations'
                )
                    ->where(
                        'id',
                        $quotation->id
                    )
                    ->where(
                        'tenant_id',
                        $tenantId
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $lockedQuotation) {
                    throw new RuntimeException(
                        'QUOTATION_NOT_FOUND'
                    );
                }

                if (
                    $lockedQuotation->status
                    !== 'APPROVED'
                ) {
                    throw new RuntimeException(
                        'QUOTATION_NOT_APPROVED'
                    );
                }

                if (
                    $lockedQuotation->current_version_id
                    !== $version->id
                ) {
                    throw new RuntimeException(
                        'QUOTATION_VERSION_CHANGED'
                    );
                }

                $existing = Invoice::query()
                    ->where(
                        'tenant_id',
                        $tenantId
                    )
                    ->where(
                        'source_quotation_version_id',
                        $version->id
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing->load([
                        'items',
                        'statusHistory',
                    ]);
                }

                $invoiceId =
                    (string) Str::ulid();

                $invoice = Invoice::query()
                    ->create([
                        'id' => $invoiceId,
                        'tenant_id' => $tenantId,
                        'invoice_number' =>
                            $invoiceNumber,
                        'customer_id' =>
                            $quotation->customer_id,
                        'project_id' => null,
                        'source_quotation_id' =>
                            $quotation->id,
                        'source_quotation_version_id' =>
                            $version->id,
                        'status' => 'DRAFT',
                        'issued_at' => null,
                        'due_at' => $dueAt,
                        'currency' =>
                            $version->currency,
                        'subtotal' =>
                            $version->subtotal,
                        'discount_total' =>
                            $version->discount_total,
                        'tax_total' =>
                            $version->tax_total,
                        'total' =>
                            $version->total,
                        'notes' =>
                            $version->notes,
                        'created_by_user_id' =>
                            $this->tenantContext->userId(),
                    ]);

                foreach (
                    $version->items as $item
                ) {
                    InvoiceItem::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),
                            'tenant_id' =>
                                $tenantId,
                            'invoice_id' =>
                                $invoiceId,
                            'source_quotation_item_id' =>
                                $item->id,
                            'catalog_item_id' =>
                                $item->catalog_item_id,
                            'item_type' =>
                                $item->item_type,
                            'code' =>
                                $item->code,
                            'name' =>
                                $item->name,
                            'description' =>
                                $item->description,
                            'quantity' =>
                                $item->quantity,
                            'unit_code' =>
                                $item->unit_code,
                            'unit_name' =>
                                $item->unit_name,
                            'unit_symbol' =>
                                $item->unit_symbol,
                            'unit_price' =>
                                $item->unit_price,
                            'discount_amount' =>
                                $item->discount_amount,
                            'tax_amount' =>
                                $item->tax_amount,
                            'amount' =>
                                $item->amount,
                            'sort_order' =>
                                $item->sort_order,
                        ]);
                }

                InvoiceStatusHistory::query()
                    ->create([
                        'id' =>
                            (string) Str::ulid(),
                        'tenant_id' =>
                            $tenantId,
                        'invoice_id' =>
                            $invoiceId,
                        'from_state' => null,
                        'to_state' => 'DRAFT',
                        'actor_user_id' =>
                            $this->tenantContext->userId(),
                        'reason' => null,
                        'source' => 'QUOTATION',
                        'context' => [
                            'quotation_id' =>
                                $quotation->id,
                            'quotation_version_id' =>
                                $version->id,
                        ],
                        'occurred_at' => now(),
                    ]);

                return $invoice->load([
                    'items',
                    'statusHistory',
                ]);
            }
        );
    }
}
