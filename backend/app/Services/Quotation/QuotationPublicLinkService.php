<?php

namespace App\Services\Quotation;

use App\Exceptions\Quotation\InvalidQuotationTransitionException;
use App\Models\Quotation;
use App\Models\QuotationAction;
use App\Models\QuotationPublicLink;
use App\Models\QuotationStatusHistory;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class QuotationPublicLinkService
{
    private const TOKEN_BYTES = 32;

    private const PUBLIC_PREFIX = 'PENAWARAN-';


    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function createForQuotation(
        string $quotationId,
        ?\DateTimeInterface $expiresAt = null
    ): array {
        return DB::transaction(function () use (
            $quotationId,
            $expiresAt
        ): array {
            $quotation = Quotation::query()
                ->where(
                    'tenant_id',
                    $this->tenantContext->tenantId()
                )
                ->where('id', $quotationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quotation->current_version_id === null) {
                throw new RuntimeException(
                    'Penawaran tidak memiliki versi aktif.'
                );
            }


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
                    'PUBLIC_LINK'
                );
            }

            $rawToken = $this->generateToken();

            $link = QuotationPublicLink::query()->create([
                'id' => (string) Str::ulid(),
                'tenant_id' =>
                    $this->tenantContext->tenantId(),
                'quotation_id' =>
                    $quotation->id,
                'quotation_version_id' =>
                    $quotation->current_version_id,
                'token_hash' =>
                    $this->hashToken($rawToken),
                'expires_at' =>
                    $expiresAt,
                'revoked_at' =>
                    null,
                'created_by_user_id' =>
                    $this->tenantContext->userId(),
            ]);

            return [
                'link' => $link,
                'token' => $rawToken,
                'public_url' =>
                    $this->buildPublicUrl(
                        $rawToken
                    ),
            ];
        });
    }

    public function resolveByPresentedToken(
        string $presentedToken
    ): QuotationPublicLink {
        $rawToken = $this->extractRawToken(
            $presentedToken
        );

        return QuotationPublicLink::query()
            ->where(
                'token_hash',
                $this->hashToken($rawToken)
            )
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere(
                        'expires_at',
                        '>',
                        now()
                    );
            })
            ->with([
                'quotation.customer',
                'quotationVersion.items',
            ])
            ->firstOrFail();
    }

    public function markViewed(
        string $presentedToken
    ): QuotationPublicLink {
        $rawToken = $this->extractRawToken(
            $presentedToken
        );

        $tokenHash = $this->hashToken(
            $rawToken
        );

        return DB::transaction(
            function () use (
                $tokenHash
            ): QuotationPublicLink {
                $link = QuotationPublicLink::query()
                    ->where(
                        'token_hash',
                        $tokenHash
                    )
                    ->whereNull('revoked_at')
                    ->where(function ($query): void {
                        $query
                            ->whereNull('expires_at')
                            ->orWhere(
                                'expires_at',
                                '>',
                                now()
                            );
                    })
                    ->lockForUpdate()
                    ->firstOrFail();

                $quotation = Quotation::query()
                    ->where(
                        'tenant_id',
                        $link->tenant_id
                    )
                    ->where(
                        'id',
                        $link->quotation_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $quotation->current_version_id
                    !== $link->quotation_version_id
                ) {
                    throw new RuntimeException(
                        'Public quotation version is no longer current.'
                    );
                }

                $alreadyRecorded =
                    QuotationAction::query()
                        ->where(
                            'public_link_id',
                            $link->id
                        )
                        ->where(
                            'quotation_version_id',
                            $link->quotation_version_id
                        )
                        ->where(
                            'action',
                            'VIEW'
                        )
                        ->exists();

                if ($alreadyRecorded) {
                    return $this->loadPublicLink(
                        $link
                    );
                }

                if ($quotation->status === 'SENT') {
                    $quotation->status = 'VIEWED';

                    if ($quotation->viewed_at === null) {
                        $quotation->viewed_at = now();
                    }

                    $quotation->save();

                    QuotationStatusHistory::query()->create([
                        'id' =>
                            (string) Str::ulid(),

                        'tenant_id' =>
                            $link->tenant_id,

                        'quotation_id' =>
                            $quotation->id,

                        'from_state' =>
                            'SENT',

                        'to_state' =>
                            'VIEWED',

                        'actor_user_id' =>
                            null,

                        'reason' =>
                            null,

                        'source' =>
                            'PUBLIC',

                        'context' => [
                            'public_link_id' =>
                                $link->id,

                            'quotation_version_id' =>
                                $link->quotation_version_id,
                        ],

                        'occurred_at' =>
                            now(),
                    ]);
                } elseif (
                    $quotation->status !== 'VIEWED'
                ) {
                    throw new InvalidQuotationTransitionException(
                        $quotation->status,
                        'VIEWED'
                    );
                }

                QuotationAction::query()->create([
                    'id' =>
                        (string) Str::ulid(),

                    'tenant_id' =>
                        $link->tenant_id,

                    'quotation_id' =>
                        $link->quotation_id,

                    'quotation_version_id' =>
                        $link->quotation_version_id,

                    'public_link_id' =>
                        $link->id,

                    'action' =>
                        'VIEW',

                    'actor_type' =>
                        'PUBLIC',

                    'actor_user_id' =>
                        null,

                    'note' =>
                        null,

                    'context' =>
                        [],

                    'occurred_at' =>
                        now(),
                ]);

                return $this->loadPublicLink(
                    $link
                );
            }
        );
    }

    private function loadPublicLink(
        QuotationPublicLink $link
    ): QuotationPublicLink {
        return $link
            ->fresh([
                'quotation.customer',
                'quotationVersion.items',
            ]);
    }

    public function buildPublicUrl(
        string $rawToken
    ): string {
        $baseUrl = rtrim(
            (string) config(
                'signova.public_quotation.base_url'
            ),
            '/'
        );

        if ($baseUrl === '') {
            throw new RuntimeException(
                'Public quotation base URL belum dikonfigurasi.'
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
        $value = trim(
            $presentedToken
        );

        if (
            str_starts_with(
                $value,
                self::PUBLIC_PREFIX
            )
        ) {
            $value = substr(
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
