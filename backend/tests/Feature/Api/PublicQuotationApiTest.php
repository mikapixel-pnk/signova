<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Services\Quotation\QuotationPublicLinkService;
use App\Tenancy\TenantContext;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicQuotationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_public_get_is_read_only_and_does_not_mark_viewed(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-get@example.test',
            'Public GET',
            'Q-PUBLIC-GET'
        );

        $response = $this->getJson(
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.quotation_number',
                'Q-PUBLIC-GET'
            )
            ->assertJsonPath(
                'data.status',
                'SENT'
            )
            ->assertJsonMissingPath(
                'data.tenant_id'
            )
            ->assertJsonMissingPath(
                'data.quotation_id'
            )
            ->assertJsonMissingPath(
                'data.version.tenant_id'
            );

        $this->assertDatabaseHas(
            'quotations',
            [
                'id' =>
                    $fixture['quotation_id'],
                'status' =>
                    'SENT',
                'viewed_at' =>
                    null,
            ]
        );

        $this->assertDatabaseMissing(
            'quotation_actions',
            [
                'public_link_id' =>
                    $fixture['public_link_id'],
                'action' =>
                    'VIEW',
            ]
        );

        $this->assertSame(
            0,
            DB::table(
                'quotation_status_history'
            )
                ->where(
                    'quotation_id',
                    $fixture['quotation_id']
                )
                ->where(
                    'to_state',
                    'VIEWED'
                )
                ->count()
        );
    }

    public function test_public_view_transitions_sent_to_viewed_once(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-view@example.test',
            'Public VIEW',
            'Q-PUBLIC-VIEW'
        );

        $response = $this->postJson(
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
            . '/actions/view'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.status',
                'VIEWED'
            );

        $quotation = DB::table(
            'quotations'
        )
            ->where(
                'id',
                $fixture['quotation_id']
            )
            ->first();

        $this->assertSame(
            'VIEWED',
            $quotation->status
        );

        $this->assertNotNull(
            $quotation->viewed_at
        );

        $this->assertDatabaseHas(
            'quotation_actions',
            [
                'tenant_id' =>
                    $fixture['tenant_id'],
                'quotation_id' =>
                    $fixture['quotation_id'],
                'quotation_version_id' =>
                    $fixture['version_id'],
                'public_link_id' =>
                    $fixture['public_link_id'],
                'action' =>
                    'VIEW',
                'actor_type' =>
                    'PUBLIC',
                'actor_user_id' =>
                    null,
            ]
        );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'tenant_id' =>
                    $fixture['tenant_id'],
                'quotation_id' =>
                    $fixture['quotation_id'],
                'from_state' =>
                    'SENT',
                'to_state' =>
                    'VIEWED',
                'actor_user_id' =>
                    null,
                'source' =>
                    'PUBLIC',
            ]
        );
    }

    public function test_duplicate_public_view_is_idempotent(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-view-idempotent@example.test',
            'Public VIEW Idempotent',
            'Q-PUBLIC-VIEW-IDEMPOTENT'
        );

        $url =
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
            . '/actions/view';

        $this->postJson(
            $url
        )->assertOk();

        $this->postJson(
            $url
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'VIEWED'
            );

        $this->assertSame(
            1,
            DB::table(
                'quotation_actions'
            )
                ->where(
                    'public_link_id',
                    $fixture['public_link_id']
                )
                ->where(
                    'action',
                    'VIEW'
                )
                ->count()
        );

        $this->assertSame(
            1,
            DB::table(
                'quotation_status_history'
            )
                ->where(
                    'quotation_id',
                    $fixture['quotation_id']
                )
                ->where(
                    'to_state',
                    'VIEWED'
                )
                ->count()
        );
    }

    public function test_invalid_public_token_returns_not_found(): void
    {
        $response = $this->getJson(
            '/api/public/v1/quotations/'
            . 'PENAWARAN-invalid-token'
        );

        $response
            ->assertNotFound()
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_expired_public_token_returns_not_found(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-expired-api@example.test',
            'Public Expired API',
            'Q-PUBLIC-EXPIRED-API',
            now()->subMinute()
        );

        $this->getJson(
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_revoked_public_token_returns_not_found(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-revoked-api@example.test',
            'Public Revoked API',
            'Q-PUBLIC-REVOKED-API'
        );

        DB::table(
            'quotation_public_links'
        )
            ->where(
                'id',
                $fixture['public_link_id']
            )
            ->update([
                'revoked_at' =>
                    now(),
                'updated_at' =>
                    now(),
            ]);

        $this->getJson(
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_public_view_rejects_invalid_transition_atomically(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-invalid-transition@example.test',
            'Public Invalid Transition',
            'Q-PUBLIC-INVALID-TRANSITION'
        );

        DB::table('quotations')
            ->where(
                'id',
                $fixture['quotation_id']
            )
            ->update([
                'status' =>
                    'APPROVED',
                'approved_at' =>
                    now(),
                'updated_at' =>
                    now(),
            ]);

        $this->postJson(
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
            . '/actions/view'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );

        $this->assertDatabaseMissing(
            'quotation_actions',
            [
                'public_link_id' =>
                    $fixture['public_link_id'],
                'action' =>
                    'VIEW',
            ]
        );

        $this->assertSame(
            0,
            DB::table(
                'quotation_status_history'
            )
                ->where(
                    'quotation_id',
                    $fixture['quotation_id']
                )
                ->where(
                    'to_state',
                    'VIEWED'
                )
                ->count()
        );
    }


    public function test_public_approve_from_sent_succeeds(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-approve-sent@example.test',
            'Public Approve SENT',
            'Q-PUBLIC-APPROVE-SENT'
        );

        $response = $this->postJson(
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
            . '/approve'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.status',
                'APPROVED'
            );

        $quotation = DB::table('quotations')
            ->where(
                'id',
                $fixture['quotation_id']
            )
            ->first();

        $this->assertSame(
            'APPROVED',
            $quotation->status
        );

        $this->assertNotNull(
            $quotation->approved_at
        );

        $this->assertDatabaseHas(
            'quotation_actions',
            [
                'public_link_id' =>
                    $fixture['public_link_id'],
                'quotation_version_id' =>
                    $fixture['version_id'],
                'action' =>
                    'APPROVE',
                'actor_type' =>
                    'PUBLIC',
            ]
        );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'quotation_id' =>
                    $fixture['quotation_id'],
                'from_state' =>
                    'SENT',
                'to_state' =>
                    'APPROVED',
                'source' =>
                    'PUBLIC',
            ]
        );
    }

    public function test_public_approve_from_viewed_succeeds(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-approve-viewed@example.test',
            'Public Approve VIEWED',
            'Q-PUBLIC-APPROVE-VIEWED'
        );

        $viewUrl =
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
            . '/actions/view';

        $this->postJson(
            $viewUrl
        )->assertOk();

        $this->postJson(
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
            . '/approve'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'APPROVED'
            );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'quotation_id' =>
                    $fixture['quotation_id'],
                'from_state' =>
                    'VIEWED',
                'to_state' =>
                    'APPROVED',
                'source' =>
                    'PUBLIC',
            ]
        );
    }

    public function test_public_reject_requires_reason(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-reject-validation@example.test',
            'Public Reject Validation',
            'Q-PUBLIC-REJECT-VALIDATION'
        );

        $this->postJson(
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
            . '/reject',
            []
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseHas(
            'quotations',
            [
                'id' =>
                    $fixture['quotation_id'],
                'status' =>
                    'SENT',
            ]
        );

        $this->assertDatabaseMissing(
            'quotation_actions',
            [
                'public_link_id' =>
                    $fixture['public_link_id'],
                'action' =>
                    'REJECT',
            ]
        );
    }

    public function test_public_reject_with_reason_succeeds(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-reject@example.test',
            'Public Reject',
            'Q-PUBLIC-REJECT'
        );

        $reason =
            'Harga belum sesuai anggaran.';

        $response = $this->postJson(
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
            . '/reject',
            [
                'reason' =>
                    $reason,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'REJECTED'
            );

        $quotation = DB::table('quotations')
            ->where(
                'id',
                $fixture['quotation_id']
            )
            ->first();

        $this->assertSame(
            'REJECTED',
            $quotation->status
        );

        $this->assertNotNull(
            $quotation->rejected_at
        );

        $this->assertDatabaseHas(
            'quotation_actions',
            [
                'public_link_id' =>
                    $fixture['public_link_id'],
                'action' =>
                    'REJECT',
                'note' =>
                    $reason,
                'actor_type' =>
                    'PUBLIC',
            ]
        );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'quotation_id' =>
                    $fixture['quotation_id'],
                'to_state' =>
                    'REJECTED',
                'reason' =>
                    $reason,
                'source' =>
                    'PUBLIC',
            ]
        );
    }

    public function test_duplicate_public_approve_is_idempotent(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-approve-idempotent@example.test',
            'Public Approve Idempotent',
            'Q-PUBLIC-APPROVE-IDEMPOTENT'
        );

        $url =
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
            . '/approve';

        $this->postJson(
            $url
        )->assertOk();

        $this->postJson(
            $url
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'APPROVED'
            );

        $this->assertSame(
            1,
            DB::table('quotation_actions')
                ->where(
                    'public_link_id',
                    $fixture['public_link_id']
                )
                ->where(
                    'action',
                    'APPROVE'
                )
                ->count()
        );

        $this->assertSame(
            1,
            DB::table(
                'quotation_status_history'
            )
                ->where(
                    'quotation_id',
                    $fixture['quotation_id']
                )
                ->where(
                    'to_state',
                    'APPROVED'
                )
                ->count()
        );
    }

    public function test_duplicate_public_reject_is_idempotent(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-reject-idempotent@example.test',
            'Public Reject Idempotent',
            'Q-PUBLIC-REJECT-IDEMPOTENT'
        );

        $url =
            '/api/public/v1/quotations/'
            . $fixture['presented_token']
            . '/reject';

        $payload = [
            'reason' =>
                'Belum dapat disetujui.',
        ];

        $this->postJson(
            $url,
            $payload
        )->assertOk();

        $this->postJson(
            $url,
            $payload
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'REJECTED'
            );

        $this->assertSame(
            1,
            DB::table('quotation_actions')
                ->where(
                    'public_link_id',
                    $fixture['public_link_id']
                )
                ->where(
                    'action',
                    'REJECT'
                )
                ->count()
        );

        $this->assertSame(
            1,
            DB::table(
                'quotation_status_history'
            )
                ->where(
                    'quotation_id',
                    $fixture['quotation_id']
                )
                ->where(
                    'to_state',
                    'REJECTED'
                )
                ->count()
        );
    }

    public function test_approve_then_reject_is_rejected_atomically(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-approve-then-reject@example.test',
            'Approve Then Reject',
            'Q-PUBLIC-APPROVE-THEN-REJECT'
        );

        $base =
            '/api/public/v1/quotations/'
            . $fixture['presented_token'];

        $this->postJson(
            $base . '/approve'
        )->assertOk();

        $this->postJson(
            $base . '/reject',
            [
                'reason' =>
                    'Berubah pikiran.',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );

        $this->assertDatabaseHas(
            'quotations',
            [
                'id' =>
                    $fixture['quotation_id'],
                'status' =>
                    'APPROVED',
            ]
        );

        $this->assertDatabaseMissing(
            'quotation_actions',
            [
                'public_link_id' =>
                    $fixture['public_link_id'],
                'action' =>
                    'REJECT',
            ]
        );

        $this->assertSame(
            1,
            DB::table(
                'quotation_status_history'
            )
                ->where(
                    'quotation_id',
                    $fixture['quotation_id']
                )
                ->where(
                    'to_state',
                    'APPROVED'
                )
                ->count()
        );

        $this->assertSame(
            0,
            DB::table(
                'quotation_status_history'
            )
                ->where(
                    'quotation_id',
                    $fixture['quotation_id']
                )
                ->where(
                    'to_state',
                    'REJECTED'
                )
                ->count()
        );
    }

    public function test_reject_then_approve_is_rejected_atomically(): void
    {
        $fixture = $this->publicQuotationFixture(
            'public-reject-then-approve@example.test',
            'Reject Then Approve',
            'Q-PUBLIC-REJECT-THEN-APPROVE'
        );

        $base =
            '/api/public/v1/quotations/'
            . $fixture['presented_token'];

        $this->postJson(
            $base . '/reject',
            [
                'reason' =>
                    'Belum sesuai.',
            ]
        )->assertOk();

        $this->postJson(
            $base . '/approve'
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'INVALID_TRANSITION'
            );

        $this->assertDatabaseHas(
            'quotations',
            [
                'id' =>
                    $fixture['quotation_id'],
                'status' =>
                    'REJECTED',
            ]
        );

        $this->assertDatabaseMissing(
            'quotation_actions',
            [
                'public_link_id' =>
                    $fixture['public_link_id'],
                'action' =>
                    'APPROVE',
            ]
        );

        $this->assertSame(
            1,
            DB::table(
                'quotation_status_history'
            )
                ->where(
                    'quotation_id',
                    $fixture['quotation_id']
                )
                ->where(
                    'to_state',
                    'REJECTED'
                )
                ->count()
        );

        $this->assertSame(
            0,
            DB::table(
                'quotation_status_history'
            )
                ->where(
                    'quotation_id',
                    $fixture['quotation_id']
                )
                ->where(
                    'to_state',
                    'APPROVED'
                )
                ->count()
        );
    }

    private function publicQuotationFixture(
        string $email,
        string $tenantName,
        string $quotationNumber,
        ?\DateTimeInterface $expiresAt = null
    ): array {
        $workspace = app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Public Quotation API Test',
            'email' =>
                $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' =>
                $tenantName,
        ]);

        $tenantId =
            $workspace['tenant_id'];

        $customerId =
            (string) Str::ulid();

        DB::table('customers')->insert([
            'id' =>
                $customerId,
            'tenant_id' =>
                $tenantId,
            'type' =>
                'COMPANY',
            'code' =>
                'C-' . $quotationNumber,
            'name' =>
                'Customer ' . $quotationNumber,
            'payment_terms_days' =>
                0,
            'status' =>
                'ACTIVE',
            'created_at' =>
                now(),
            'updated_at' =>
                now(),
        ]);

        $quotationId =
            (string) Str::ulid();

        DB::table('quotations')->insert([
            'id' =>
                $quotationId,
            'tenant_id' =>
                $tenantId,
            'quotation_number' =>
                $quotationNumber,
            'customer_id' =>
                $customerId,
            'status' =>
                'SENT',
            'source' =>
                'MANUAL',
            'sent_at' =>
                now(),
            'created_at' =>
                now(),
            'updated_at' =>
                now(),
        ]);

        $versionId =
            (string) Str::ulid();

        DB::table('quotation_versions')
            ->insert([
                'id' =>
                    $versionId,
                'tenant_id' =>
                    $tenantId,
                'quotation_id' =>
                    $quotationId,
                'revision_no' =>
                    1,
                'subtotal' =>
                    100000,
                'discount_total' =>
                    0,
                'tax_total' =>
                    0,
                'total' =>
                    100000,
                'currency' =>
                    'IDR',
                'terms' =>
                    'Pembayaran sesuai kesepakatan.',
                'notes' =>
                    'Catatan publik.',
                'created_at' =>
                    now(),
                'updated_at' =>
                    now(),
            ]);

        DB::table('quotation_items')
            ->insert([
                'id' =>
                    (string) Str::ulid(),
                'tenant_id' =>
                    $tenantId,
                'quotation_version_id' =>
                    $versionId,
                'catalog_item_id' =>
                    null,
                'unit_id' =>
                    null,
                'item_type' =>
                    'SERVICE',
                'code' =>
                    'ITEM-001',
                'name' =>
                    'Pembuatan Signage',
                'description' =>
                    'Snapshot item quotation.',
                'quantity' =>
                    1,
                'unit_code' =>
                    'PCS',
                'unit_name' =>
                    'Pieces',
                'unit_symbol' =>
                    'pcs',
                'pricing_method' =>
                    'STANDARD',
                'pricing_config' =>
                    json_encode([]),
                'unit_price' =>
                    100000,
                'discount_amount' =>
                    0,
                'tax_amount' =>
                    0,
                'amount' =>
                    100000,
                'sort_order' =>
                    1,
                'created_at' =>
                    now(),
                'updated_at' =>
                    now(),
            ]);

        DB::table('quotations')
            ->where(
                'id',
                $quotationId
            )
            ->update([
                'current_version_id' =>
                    $versionId,
                'updated_at' =>
                    now(),
            ]);

        app(
            TenantContext::class
        )->set(
            $tenantId,
            $workspace['user_id']
        );

        $created = app(
            QuotationPublicLinkService::class
        )->createForQuotation(
            $quotationId,
            $expiresAt
        );

        app(
            TenantContext::class
        )->clear();

        return [
            'tenant_id' =>
                $tenantId,
            'quotation_id' =>
                $quotationId,
            'version_id' =>
                $versionId,
            'public_link_id' =>
                $created['link']->id,
            'presented_token' =>
                basename(
                    $created['public_url']
                ),
        ];
    }
}
