<?php

namespace App\Services\Document;

use App\Models\TenantDocumentSetting;
use App\Models\TenantSequence;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentNumberService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext
    ) {
    }

    public function nextQuotationNumber(): string
    {
        return $this->nextConfiguredNumber(
            documentType: 'QUOTATION',
            defaultPrefix: 'PNW',
            defaultPadding: 4
        );
    }

    public function nextInvoiceNumber(): string
    {
        return $this->nextConfiguredNumber(
            documentType: 'INVOICE',
            defaultPrefix: 'INV',
            defaultPadding: 4
        );
    }

    public function nextPurchaseRequestNumber(): string
    {
        return $this->nextDefaultNumber(
            documentType: 'PURCHASE_REQUEST',
            prefix: 'PR',
            defaultPadding: 4
        );
    }

    public function nextPurchaseOrderNumber(): string
    {
        return $this->nextDefaultNumber(
            documentType: 'PURCHASE_ORDER',
            prefix: 'PO',
            defaultPadding: 4
        );
    }

    public function nextGoodsReceiptNumber(): string
    {
        return $this->nextDefaultNumber(
            documentType: 'GOODS_RECEIPT',
            prefix: 'GR',
            defaultPadding: 4
        );
    }

    public function nextSupplierBillNumber(): string
    {
        return $this->nextDefaultNumber(
            documentType: 'SUPPLIER_BILL',
            prefix: 'SB',
            defaultPadding: 4
        );
    }

    private function nextDefaultNumber(
        string $documentType,
        string $prefix,
        int $defaultPadding
    ): string {
        $tenantId =
            $this->tenantContext->tenantId();

        $timezone =
            DB::table('tenants')
                ->where(
                    'id',
                    $tenantId
                )
                ->value('timezone')
            ?? config(
                'app.timezone',
                'UTC'
            );

        $period =
            CarbonImmutable::now(
                $timezone
            )->format('Ym');

        return $this->next(
            documentType: $documentType,
            period: $period,
            prefix:
                strtoupper(
                    trim($prefix)
                )
                . '-'
                . $period
                . '-',
            defaultPadding: $defaultPadding
        );
    }

    private function nextConfiguredNumber(
        string $documentType,
        string $defaultPrefix,
        int $defaultPadding
    ): string {
        $tenantId =
            $this->tenantContext->tenantId();

        $businessId =
            $this->businessContext->businessId();

        $timezone =
            DB::table('tenants')
                ->where(
                    'id',
                    $tenantId
                )
                ->value('timezone')
            ?? config(
                'app.timezone',
                'UTC'
            );

        $now =
            CarbonImmutable::now(
                $timezone
            );

        $period =
            $now->format('Ym');

        $prefixColumn =
            $documentType === 'QUOTATION'
                ? 'quotation_number_prefix'
                : 'invoice_number_prefix';

        $configuredPrefix =
            TenantDocumentSetting::query()
                ->where(
                    'tenant_id',
                    $tenantId
                )
                ->where(
                    'business_id',
                    $businessId
                )
                ->value(
                    $prefixColumn
                );

        $prefix =
            strtoupper(
                trim(
                    (string) (
                        $configuredPrefix
                        ?: $defaultPrefix
                    )
                )
            );

        if ($prefix === '') {
            $prefix =
                $defaultPrefix;
        }

        return $this->next(
            documentType: $documentType,
            period: $period,
            prefix:
                $prefix
                . '-'
                . $period
                . '-',
            defaultPadding: $defaultPadding
        );
    }

    public function next(
        string $documentType,
        string $period,
        string $prefix,
        int $defaultPadding = 4
    ): string {
        $tenantId =
            $this->tenantContext->tenantId();

        $businessId =
            $this->businessContext->businessId();

        return DB::transaction(
            function () use (
                $tenantId,
                $businessId,
                $documentType,
                $period,
                $prefix,
                $defaultPadding
            ): string {
                TenantSequence::query()
                    ->insertOrIgnore([
                        'id' =>
                            (string) Str::ulid(),

                        'tenant_id' =>
                            $tenantId,

                        'business_id' =>
                            $businessId,

                        'document_type' =>
                            $documentType,

                        'period' =>
                            $period,

                        'prefix' =>
                            $prefix,

                        'next_number' =>
                            1,

                        'padding' =>
                            $defaultPadding,

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                $sequence =
                    TenantSequence::query()
                        ->where(
                            'tenant_id',
                            $tenantId
                        )
                        ->where(
                            'business_id',
                            $businessId
                        )
                        ->where(
                            'document_type',
                            $documentType
                        )
                        ->where(
                            'period',
                            $period
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                 * Prefix config dapat berubah di tengah periode.
                 * Counter tetap melanjutkan next_number yang sama.
                 */
                if (
                    $sequence->prefix !==
                    $prefix
                ) {
                    $sequence->prefix =
                        $prefix;
                }

                $number =
                    $sequence->prefix
                    . str_pad(
                        (string)
                        $sequence->next_number,
                        $sequence->padding,
                        '0',
                        STR_PAD_LEFT
                    );

                $sequence->next_number =
                    $sequence->next_number + 1;

                $sequence->save();

                return $number;
            }
        );
    }
}
