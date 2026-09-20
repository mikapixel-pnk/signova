<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_owner_can_create_draft_with_server_owned_number_snapshot_and_total(): void
    {
        $workspace =
            $this->workspace(
                'pr-create@example.test',
                'PR Create'
            );

        $unitId =
            $this->insertUnit(
                $workspace,
                'PCS-PR',
                'Pieces PR',
                'pcs'
            );

        $catalogItemId =
            $this->insertCatalogItem(
                $workspace,
                $unitId,
                'PR-ITEM-001',
                'Akrilik 5mm',
                'PRODUCT'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/purchasing/requests',
                [
                    'needed_at' =>
                        '2026-09-25',

                    'currency' =>
                        'idr',

                    'notes' =>
                        'Bahan produksi',

                    'items' => [
                        [
                            'catalog_item_id' =>
                                $catalogItemId,

                            'quantity' =>
                                2.5,

                            'estimated_unit_price' =>
                                12000,
                        ],
                    ],
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            )
            ->assertJsonPath(
                'data.currency',
                'IDR'
            )
            ->assertJsonPath(
                'data.estimated_total',
                '30000.00'
            )
            ->assertJsonPath(
                'data.items.0.catalog_item_id',
                $catalogItemId
            )
            ->assertJsonPath(
                'data.items.0.code',
                'PR-ITEM-001'
            )
            ->assertJsonPath(
                'data.items.0.name',
                'Akrilik 5mm'
            )
            ->assertJsonPath(
                'data.items.0.item_type',
                'PRODUCT'
            )
            ->assertJsonPath(
                'data.items.0.unit_code',
                'PCS-PR'
            )
            ->assertJsonPath(
                'data.items.0.unit_name',
                'Pieces PR'
            )
            ->assertJsonPath(
                'data.items.0.unit_symbol',
                'pcs'
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                '2.5000'
            )
            ->assertJsonPath(
                'data.items.0.estimated_unit_price',
                '12000.00'
            )
            ->assertJsonPath(
                'data.items.0.amount',
                '30000.00'
            )
            ->assertJsonMissingPath(
                'data.tenant_id'
            )
            ->assertJsonMissingPath(
                'data.business_id'
            );

        $requestNumber =
            $response->json(
                'data.request_number'
            );

        $this->assertIsString(
            $requestNumber
        );

        $this->assertMatchesRegularExpression(
            '/^PR-\d{6}-\d{4}$/',
            $requestNumber
        );

        $purchaseRequestId =
            $response->json(
                'data.id'
            );

        $this->assertDatabaseHas(
            'purchase_requests',
            [
                'id' =>
                    $purchaseRequestId,

                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $workspace['business_id'],

                'status' =>
                    'DRAFT',

                'estimated_total' =>
                    '30000.00',

                'requested_by_user_id' =>
                    $workspace['user_id'],
            ]
        );

        $this->assertDatabaseHas(
            'purchase_request_status_history',
            [
                'purchase_request_id' =>
                    $purchaseRequestId,

                'from_status' =>
                    null,

                'to_status' =>
                    'DRAFT',

                'action' =>
                    'CREATED',

                'actor_user_id' =>
                    $workspace['user_id'],
            ]
        );
    }

    public function test_create_normalizes_money_before_amount_calculation(): void
    {
        $workspace =
            $this->workspace(
                'pr-precision@example.test',
                'PR Precision'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/purchasing/requests',
                [
                    'currency' =>
                        'IDR',

                    'items' => [
                        [
                            'name' =>
                                'Precision Item',

                            'item_type' =>
                                'PRODUCT',

                            'quantity' =>
                                '3',

                            'estimated_unit_price' =>
                                '0.005',
                        ],
                    ],
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.items.0.quantity',
                '3.0000'
            )
            ->assertJsonPath(
                'data.items.0.estimated_unit_price',
                '0.01'
            )
            ->assertJsonPath(
                'data.items.0.amount',
                '0.03'
            )
            ->assertJsonPath(
                'data.estimated_total',
                '0.03'
            );
    }

    public function test_draft_can_be_updated_and_total_is_recalculated_by_backend(): void
    {
        $workspace =
            $this->workspace(
                'pr-update@example.test',
                'PR Update'
            );

        $purchaseRequestId =
            $this->createDraft(
                $workspace
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}",
            [
                'notes' =>
                    'Revisi kebutuhan',

                'items' => [
                    [
                        'name' =>
                            'Bahan Custom',

                        'item_type' =>
                            'PRODUCT',

                        'quantity' =>
                            4,

                        'estimated_unit_price' =>
                            7500,
                    ],
                ],
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            )
            ->assertJsonPath(
                'data.notes',
                'Revisi kebutuhan'
            )
            ->assertJsonPath(
                'data.estimated_total',
                '30000.00'
            )
            ->assertJsonPath(
                'data.items.0.name',
                'Bahan Custom'
            )
            ->assertJsonPath(
                'data.items.0.amount',
                '30000.00'
            );

        $this->assertSame(
            1,
            DB::table(
                'purchase_request_items'
            )
                ->where(
                    'purchase_request_id',
                    $purchaseRequestId
                )
                ->count()
        );
    }

    public function test_submit_then_approve_records_history_without_financial_or_po_side_effects(): void
    {
        $workspace =
            $this->workspace(
                'pr-approve@example.test',
                'PR Approve'
            );

        $cashBefore =
            DB::table(
                'cash_transactions'
            )->count();

        $poBefore =
            DB::table(
                'purchase_orders'
            )->count();

        $purchaseRequestId =
            $this->createDraft(
                $workspace
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}/actions/submit"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'SUBMITTED'
            );

        $this->postJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}/actions/approve"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'APPROVED'
            );

        $this->assertDatabaseHas(
            'purchase_requests',
            [
                'id' =>
                    $purchaseRequestId,

                'status' =>
                    'APPROVED',

                'submitted_by_user_id' =>
                    $workspace['user_id'],

                'approved_by_user_id' =>
                    $workspace['user_id'],
            ]
        );

        $history =
            DB::table(
                'purchase_request_status_history'
            )
                ->where(
                    'purchase_request_id',
                    $purchaseRequestId
                )
                ->orderBy(
                    'created_at'
                )
                ->pluck(
                    'action'
                )
                ->all();

        $this->assertSame(
            [
                'CREATED',
                'SUBMITTED',
                'APPROVED',
            ],
            $history
        );

        $this->assertSame(
            $cashBefore,
            DB::table(
                'cash_transactions'
            )->count()
        );

        $this->assertSame(
            $poBefore,
            DB::table(
                'purchase_orders'
            )->count()
        );
    }

    public function test_submitted_request_can_be_rejected_then_revised_to_draft(): void
    {
        $workspace =
            $this->workspace(
                'pr-reject@example.test',
                'PR Reject'
            );

        $purchaseRequestId =
            $this->createDraft(
                $workspace
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}/actions/submit"
        )->assertOk();

        $this->postJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}/actions/reject",
            [
                'reason' =>
                    'Jumlah belum sesuai.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'REJECTED'
            )
            ->assertJsonPath(
                'data.rejection_reason',
                'Jumlah belum sesuai.'
            );

        $this->postJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}/actions/revise"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'DRAFT'
            )
            ->assertJsonPath(
                'data.rejection_reason',
                null
            );

        $this->assertDatabaseHas(
            'purchase_request_status_history',
            [
                'purchase_request_id' =>
                    $purchaseRequestId,

                'to_status' =>
                    'REJECTED',

                'action' =>
                    'REJECTED',

                'reason' =>
                    'Jumlah belum sesuai.',
            ]
        );

        $this->assertDatabaseHas(
            'purchase_request_status_history',
            [
                'purchase_request_id' =>
                    $purchaseRequestId,

                'from_status' =>
                    'REJECTED',

                'to_status' =>
                    'DRAFT',

                'action' =>
                    'REVISED',
            ]
        );
    }

    public function test_draft_submitted_and_rejected_requests_can_be_cancelled(): void
    {
        $workspace =
            $this->workspace(
                'pr-cancel@example.test',
                'PR Cancel'
            );

        /*
         * DRAFT
         */
        $draftId =
            $this->createDraft(
                $workspace
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/purchasing/requests/{$draftId}/actions/cancel",
            [
                'reason' =>
                    'Tidak jadi dibutuhkan.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'CANCELLED'
            );

        /*
         * SUBMITTED
         */
        $submittedId =
            $this->createDraft(
                $workspace
            );

        $this->postJson(
            "/api/v1/purchasing/requests/{$submittedId}/actions/submit"
        )->assertOk();

        $this->postJson(
            "/api/v1/purchasing/requests/{$submittedId}/actions/cancel",
            [
                'reason' =>
                    'Kebutuhan dibatalkan.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'CANCELLED'
            );

        /*
         * REJECTED
         */
        $rejectedId =
            $this->createDraft(
                $workspace
            );

        $this->postJson(
            "/api/v1/purchasing/requests/{$rejectedId}/actions/submit"
        )->assertOk();

        $this->postJson(
            "/api/v1/purchasing/requests/{$rejectedId}/actions/reject",
            [
                'reason' =>
                    'Spesifikasi belum tepat.',
            ]
        )->assertOk();

        $this->postJson(
            "/api/v1/purchasing/requests/{$rejectedId}/actions/cancel",
            [
                'reason' =>
                    'Permintaan tidak dilanjutkan.',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'CANCELLED'
            );
    }

    public function test_invalid_transition_returns_entity_state_conflict_and_approved_is_not_editable(): void
    {
        $workspace =
            $this->workspace(
                'pr-state@example.test',
                'PR State'
            );

        $purchaseRequestId =
            $this->createDraft(
                $workspace
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}/actions/approve"
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );

        $this->postJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}/actions/submit"
        )->assertOk();

        $this->postJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}/actions/approve"
        )->assertOk();

        $this->patchJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}",
            [
                'notes' =>
                    'Tidak boleh berubah',
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'ENTITY_STATE_CONFLICT'
            );
    }

    public function test_request_capability_is_enforced(): void
    {
        $workspace =
            $this->workspace(
                'pr-request-denied@example.test',
                'PR Request Denied'
            );

        $this->revokeOwnerCapability(
            $workspace,
            'purchasing.request'
        );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/purchasing/requests',
            $this->defaultPayload()
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_approval_capability_is_separate_from_request_capability(): void
    {
        $workspace =
            $this->workspace(
                'pr-approve-denied@example.test',
                'PR Approve Denied'
            );

        $purchaseRequestId =
            $this->createDraft(
                $workspace
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}/actions/submit"
        )->assertOk();

        $this->revokeOwnerCapability(
            $workspace,
            'purchasing.approve_request'
        );

        $this->postJson(
            "/api/v1/purchasing/requests/{$purchaseRequestId}/actions/approve"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );

        $this->assertDatabaseHas(
            'purchase_requests',
            [
                'id' =>
                    $purchaseRequestId,

                'status' =>
                    'SUBMITTED',
            ]
        );
    }

    public function test_purchase_request_is_hidden_across_tenants(): void
    {
        $first =
            $this->workspace(
                'pr-tenant-first@example.test',
                'PR Tenant First'
            );

        $second =
            $this->workspace(
                'pr-tenant-second@example.test',
                'PR Tenant Second'
            );

        $foreignId =
            $this->createDraft(
                $second
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            "/api/v1/purchasing/requests/{$foreignId}"
        )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'RESOURCE_NOT_FOUND'
            );
    }

    public function test_purchase_requests_are_isolated_between_businesses(): void
    {
        $workspace =
            $this->workspace(
                'pr-business@example.test',
                'PR Business'
            );

        $otherBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'Usaha Kedua PR'
            );

        $foreignId =
            $this->createDraft(
                $otherBusiness
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $list =
            $this->getJson(
                '/api/v1/purchasing/requests'
            )
                ->assertOk();

        $this->assertFalse(
            collect(
                $list->json('data')
            )->contains(
                fn (array $row) =>
                    $row['id']
                    === $foreignId
            )
        );

        $this->getJson(
            "/api/v1/purchasing/requests/{$foreignId}"
        )->assertNotFound();
    }

    public function test_catalog_item_from_other_business_is_rejected(): void
    {
        $workspace =
            $this->workspace(
                'pr-catalog-scope@example.test',
                'PR Catalog Scope'
            );

        $otherBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'PR Catalog Business B'
            );

        $foreignCatalogId =
            $this->insertCatalogItem(
                $otherBusiness,
                null,
                'FOREIGN-CATALOG-PR',
                'Item Business B',
                'PRODUCT'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/purchasing/requests',
            [
                'items' => [
                    [
                        'catalog_item_id' =>
                            $foreignCatalogId,

                        'quantity' =>
                            1,

                        'estimated_unit_price' =>
                            10000,
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => [
                            'items.0.catalog_item_id',
                        ],
                    ],
                ],
            ]);
    }

    public function test_unit_from_other_business_is_rejected(): void
    {
        $workspace =
            $this->workspace(
                'pr-unit-scope@example.test',
                'PR Unit Scope'
            );

        $otherBusiness =
            $this->secondBusinessWorkspace(
                $workspace,
                'PR Unit Business B'
            );

        $foreignUnitId =
            $this->insertUnit(
                $otherBusiness,
                'BOX-FOREIGN',
                'Box Foreign',
                'box'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->postJson(
            '/api/v1/purchasing/requests',
            [
                'items' => [
                    [
                        'unit_id' =>
                            $foreignUnitId,

                        'name' =>
                            'Item Custom',

                        'item_type' =>
                            'PRODUCT',

                        'quantity' =>
                            1,

                        'estimated_unit_price' =>
                            5000,
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            )
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'fields' => [
                            'items.0.unit_id',
                        ],
                    ],
                ],
            ]);
    }

    public function test_client_cannot_inject_server_owned_fields(): void
    {
        $workspace =
            $this->workspace(
                'pr-owned-fields@example.test',
                'PR Owned Fields'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $payload =
            $this->defaultPayload();

        $payload['status'] =
            'APPROVED';

        $payload['request_number'] =
            'PR-FAKE';

        $payload['estimated_total'] =
            1;

        $payload['requested_by_user_id'] =
            (string) Str::ulid();

        $payload['items'][0]['amount'] =
            1;

        $this->postJson(
            '/api/v1/purchasing/requests',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertSame(
            0,
            DB::table(
                'purchase_requests'
            )->count()
        );
    }

    private function createDraft(
        array $workspace,
        ?array $payload = null
    ): string {
        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->postJson(
                '/api/v1/purchasing/requests',
                $payload
                    ?? $this->defaultPayload()
            )
                ->assertCreated()
                ->assertJsonPath(
                    'data.status',
                    'DRAFT'
                );

        return (string) $response->json(
            'data.id'
        );
    }

    private function defaultPayload(): array
    {
        return [
            'currency' =>
                'IDR',

            'notes' =>
                'Permintaan pembelian test',

            'items' => [
                [
                    'name' =>
                        'Bahan Test',

                    'item_type' =>
                        'PRODUCT',

                    'quantity' =>
                        2,

                    'estimated_unit_price' =>
                        10000,
                ],
            ],
        ];
    }

    private function actingAsWorkspace(
        array $workspace
    ): void {
        Sanctum::actingAs(
            User::findOrFail(
                $workspace['user_id']
            )
        );

        $this->withHeader(
            'X-Signova-Tenant',
            $workspace['tenant_id']
        );

        $this->withHeader(
            'X-Signova-Business',
            $workspace['business_id']
        );
    }

    private function revokeOwnerCapability(
        array $workspace,
        string $capabilityCode
    ): void {
        $capabilityId =
            DB::table(
                'capabilities'
            )
                ->where(
                    'code',
                    $capabilityCode
                )
                ->value('id');

        $this->assertNotNull(
            $capabilityId,
            "Capability {$capabilityCode} harus tersedia."
        );

        DB::table(
            'role_capabilities'
        )
            ->where(
                'role_id',
                $workspace[
                    'owner_role_id'
                ]
            )
            ->where(
                'capability_id',
                $capabilityId
            )
            ->delete();
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Purchase Request API Owner',

            'email' =>
                $email,

            'password' =>
                'SecurePassword123!',

            'tenant_name' =>
                $tenantName,

            'timezone' =>
                'Asia/Jakarta',
        ]);
    }

    private function secondBusinessWorkspace(
        array $workspace,
        string $name
    ): array {
        $businessId =
            (string) Str::ulid();

        DB::table(
            'business_profiles'
        )->insert([
            'id' =>
                $businessId,

            'tenant_id' =>
                $workspace['tenant_id'],

            'name' =>
                $name,

            'is_default' =>
                false,

            'status' =>
                'ACTIVE',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return [
            ...$workspace,

            'business_id' =>
                $businessId,
        ];
    }

    private function insertUnit(
        array $workspace,
        string $code,
        string $name,
        ?string $symbol
    ): string {
        $id =
            (string) Str::ulid();

        DB::table(
            'units'
        )->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'code' =>
                $code,

            'name' =>
                $name,

            'symbol' =>
                $symbol,

            'unit_type' =>
                'OTHER',

            'decimal_precision' =>
                4,

            'status' =>
                'ACTIVE',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }

    private function insertCatalogItem(
        array $workspace,
        ?string $unitId,
        string $code,
        string $name,
        string $type
    ): string {
        $id =
            (string) Str::ulid();

        DB::table(
            'catalog_items'
        )->insert([
            'id' =>
                $id,

            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'category_id' =>
                null,

            'unit_id' =>
                $unitId,

            'type' =>
                $type,

            'code' =>
                $code,

            'name' =>
                $name,

            'description' =>
                null,

            'pricing_method' =>
                'STANDARD',

            'base_price' =>
                '0.00',

            'currency' =>
                'IDR',

            'pricing_config' =>
                null,

            'status' =>
                'ACTIVE',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return $id;
    }
}
