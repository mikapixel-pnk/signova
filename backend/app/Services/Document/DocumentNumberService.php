<?php

namespace App\Services\Document;

use App\Models\TenantSequence;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentNumberService
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function nextInvoiceNumber(): string
    {
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

        $now =
            CarbonImmutable::now(
                $timezone
            );

        $period =
            $now->format('Ym');

        return $this->next(
            documentType: 'INVOICE',
            period: $period,
            defaultPrefix:
                'INV-' . $period . '-',
            defaultPadding: 4
        );
    }

    public function next(
        string $documentType,
        string $period,
        string $defaultPrefix,
        int $defaultPadding = 4
    ): string {
        $tenantId =
            $this->tenantContext->tenantId();

        return DB::transaction(
            function () use (
                $tenantId,
                $documentType,
                $period,
                $defaultPrefix,
                $defaultPadding
            ): string {
                TenantSequence::query()
                    ->insertOrIgnore([
                        'id' =>
                            (string) Str::ulid(),

                        'tenant_id' =>
                            $tenantId,

                        'document_type' =>
                            $documentType,

                        'period' =>
                            $period,

                        'prefix' =>
                            $defaultPrefix,

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
                            'document_type',
                            $documentType
                        )
                        ->where(
                            'period',
                            $period
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

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
