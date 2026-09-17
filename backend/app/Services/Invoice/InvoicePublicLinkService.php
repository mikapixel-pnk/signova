<?php

namespace App\Services\Invoice;

use App\Exceptions\Invoice\InvalidInvoiceTransitionException;
use App\Models\Invoice;
use App\Models\InvoicePublicLink;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InvoicePublicLinkService
{
    private const TOKEN_BYTES = 32;

    private const PUBLIC_PREFIX =
        'TAGIHAN-';

    private const LINKABLE_STATUSES = [
        'ISSUED',
        'PARTIALLY_PAID',
        'OVERDUE',
        'PAID',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext
    ) {
    }

    public function issueForInvoice(
        string $invoiceId,
        ?\DateTimeInterface $expiresAt = null
    ): array {
        return DB::transaction(
            function () use (
                $invoiceId,
                $expiresAt
            ): array {
                $invoice =
                    Invoice::query()
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
                            $invoiceId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    ! in_array(
                        $invoice->status,
                        self::LINKABLE_STATUSES,
                        true
                    )
                ) {
                    throw new InvalidInvoiceTransitionException(
                        $invoice->status,
                        'PUBLIC_LINK'
                    );
                }

                /*
                 * Satu link aktif per Invoice.
                 *
                 * Reissue tidak menghapus histori token lama.
                 * Link lama hanya direvoke.
                 */
                InvoicePublicLink::query()
                    ->where(
                        'tenant_id',
                        $invoice->tenant_id
                    )
                    ->where(
                        'business_id',
                        $invoice->business_id
                    )
                    ->where(
                        'invoice_id',
                        $invoice->id
                    )
                    ->whereNull(
                        'revoked_at'
                    )
                    ->update([
                        'revoked_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                $rawToken =
                    $this->generateToken();

                $link =
                    InvoicePublicLink::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'tenant_id' =>
                                $invoice->tenant_id,

                            'business_id' =>
                                $invoice->business_id,

                            'invoice_id' =>
                                $invoice->id,

                            'token_hash' =>
                                $this->hashToken(
                                    $rawToken
                                ),

                            'expires_at' =>
                                $expiresAt,

                            'revoked_at' =>
                                null,

                            'created_by_user_id' =>
                                $this->tenantContext
                                    ->userId(),
                        ]);

                return [
                    'link' =>
                        $link,

                    'token' =>
                        $rawToken,

                    'public_url' =>
                        $this->buildPublicUrl(
                            $rawToken
                        ),
                ];
            }
        );
    }

    public function resolveByPresentedToken(
        string $presentedToken
    ): InvoicePublicLink {
        $rawToken =
            $this->extractRawToken(
                $presentedToken
            );

        return InvoicePublicLink::query()
            ->where(
                'token_hash',
                $this->hashToken(
                    $rawToken
                )
            )
            ->whereNull(
                'revoked_at'
            )
            ->where(
                function ($query): void {
                    $query
                        ->whereNull(
                            'expires_at'
                        )
                        ->orWhere(
                            'expires_at',
                            '>',
                            now()
                        );
                }
            )
            ->with([
                'invoice.customer',
                'invoice.items',
            ])
            ->firstOrFail();
    }

    public function buildPublicUrl(
        string $rawToken
    ): string {
        $baseUrl =
            rtrim(
                (string) config(
                    'signova.public_invoice.base_url'
                ),
                '/'
            );

        if ($baseUrl === '') {
            throw new RuntimeException(
                'Public invoice base URL belum dikonfigurasi.'
            );
        }

        return $baseUrl
            . '/'
            . self::PUBLIC_PREFIX
            . $rawToken;
    }

    private function generateToken(): string
    {
        return rtrim(
            strtr(
                base64_encode(
                    random_bytes(
                        self::TOKEN_BYTES
                    )
                ),
                '+/',
                '-_'
            ),
            '='
        );
    }

    private function hashToken(
        string $rawToken
    ): string {
        return hash(
            'sha256',
            $rawToken
        );
    }

    private function extractRawToken(
        string $presentedToken
    ): string {
        $value =
            trim(
                $presentedToken
            );

        if (
            str_starts_with(
                $value,
                self::PUBLIC_PREFIX
            )
        ) {
            $value =
                substr(
                    $value,
                    strlen(
                        self::PUBLIC_PREFIX
                    )
                );
        }

        if ($value === '') {
            throw new RuntimeException(
                'Public token tidak valid.'
            );
        }

        return $value;
    }
}
