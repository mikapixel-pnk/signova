<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Services\Quotation\QuotationService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class QuotationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_create_draft_is_atomic_with_version_items_current_version_and_history(): void
    {
        $workspace = $this->workspace(
            'quotation-draft@example.test',
            'Quotation Draft'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-001'
        );

        $catalog = $this->insertCatalogItem(
            $workspace['tenant_id'],
            'SIGN-001',
            'Neon Box',
            'SERVICE',
            'AREA'
        );

        $this->setTenantContext($workspace);

        $quotation = app(
            QuotationService::class
        )->createDraft(
            [
                'quotation_number' => 'Q-001',
                'customer_id' => $customerId,
                'valid_until' => now()
                    ->addDays(14)
                    ->toDateString(),
            ],
            [
                'subtotal' => 150000,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 150000,
                'currency' => 'IDR',
                'notes' => 'Draf awal',
            ],
            [
                [
                    'catalog_item_id' => $catalog['id'],
                    'quantity' => 2,
                    'unit_price' => 75000,
                    'pricing_config' => [
                        'width' => 1,
                        'height' => 1,
                    ],
                ],
            ]
        );

        $this->assertSame(
            'DRAFT',
            $quotation->status
        );

        $this->assertNotNull(
            $quotation->current_version_id
        );

        $this->assertSame(
            1,
            $quotation->currentVersion->revision_no
        );

        $this->assertCount(
            1,
            $quotation->currentVersion->items
        );

        $item =
            $quotation->currentVersion->items->first();

        $this->assertSame(
            'Neon Box',
            $item->name
        );

        $this->assertSame(
            'AREA',
            $item->pricing_method
        );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'quotation_id' =>
                    $quotation->id,
                'from_state' => null,
                'to_state' => 'DRAFT',
                'actor_user_id' =>
                    $workspace['user_id'],
            ]
        );
    }

    public function test_manual_custom_line_is_supported(): void
    {
        $workspace = $this->workspace(
            'quotation-manual@example.test',
            'Quotation Manual'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-MANUAL'
        );

        $this->setTenantContext($workspace);

        $quotation = app(
            QuotationService::class
        )->createDraft(
            [
                'quotation_number' => 'Q-MANUAL',
                'customer_id' => $customerId,
            ],
            [
                'subtotal' => 50000,
                'total' => 50000,
            ],
            [
                [
                    'name' => 'Jasa Survey Lokasi',
                    'item_type' => 'SERVICE',
                    'pricing_method' => 'MANUAL',
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'amount' => 50000,
                ],
            ]
        );

        $item =
            $quotation->currentVersion->items->first();

        $this->assertNull(
            $item->catalog_item_id
        );

        $this->assertSame(
            'Jasa Survey Lokasi',
            $item->name
        );

        $this->assertSame(
            'MANUAL',
            $item->pricing_method
        );
    }

    public function test_cross_tenant_customer_is_rejected(): void
    {
        $first = $this->workspace(
            'quotation-customer-first@example.test',
            'Quotation Customer First'
        );

        $second = $this->workspace(
            'quotation-customer-second@example.test',
            'Quotation Customer Second'
        );

        $foreignCustomerId =
            $this->insertCustomer(
                $second['tenant_id'],
                'FOREIGN-CUSTOMER'
            );

        $this->setTenantContext($first);

        $this->expectException(
            ModelNotFoundException::class
        );

        app(
            QuotationService::class
        )->createDraft(
            [
                'quotation_number' => 'Q-CROSS-CUST',
                'customer_id' =>
                    $foreignCustomerId,
            ],
            [
                'subtotal' => 10000,
                'total' => 10000,
            ],
            [
                [
                    'name' => 'Manual Item',
                    'pricing_method' => 'MANUAL',
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'amount' => 10000,
                ],
            ]
        );
    }

    public function test_cross_tenant_catalog_item_is_rejected_and_draft_rolls_back(): void
    {
        $first = $this->workspace(
            'quotation-catalog-first@example.test',
            'Quotation Catalog First'
        );

        $second = $this->workspace(
            'quotation-catalog-second@example.test',
            'Quotation Catalog Second'
        );

        $customerId = $this->insertCustomer(
            $first['tenant_id'],
            'CUST-CROSS-CAT'
        );

        $foreignCatalog =
            $this->insertCatalogItem(
                $second['tenant_id'],
                'FOREIGN-CAT',
                'Catalog Asing',
                'PRODUCT',
                'STANDARD'
            );

        $this->setTenantContext($first);

        try {
            app(
                QuotationService::class
            )->createDraft(
                [
                    'quotation_number' =>
                        'Q-CROSS-CAT',
                    'customer_id' => $customerId,
                ],
                [
                    'subtotal' => 10000,
                    'total' => 10000,
                ],
                [
                    [
                        'catalog_item_id' =>
                            $foreignCatalog['id'],
                        'quantity' => 1,
                        'unit_price' => 10000,
                        'amount' => 10000,
                    ],
                ]
            );

            $this->fail(
                'Expected ModelNotFoundException.'
            );
        } catch (ModelNotFoundException) {
            //
        }

        $this->assertDatabaseMissing(
            'quotations',
            [
                'tenant_id' =>
                    $first['tenant_id'],
                'quotation_number' =>
                    'Q-CROSS-CAT',
            ]
        );

        $this->assertDatabaseCount(
            'quotation_versions',
            0
        );

        $this->assertDatabaseCount(
            'quotation_items',
            0
        );
    }

    public function test_create_revision_preserves_old_version_and_switches_current_version(): void
    {
        $workspace = $this->workspace(
            'quotation-revision@example.test',
            'Quotation Revision'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-REV'
        );

        $this->setTenantContext($workspace);

        $service = app(
            QuotationService::class
        );

        $draft = $service->createDraft(
            [
                'quotation_number' => 'Q-REV',
                'customer_id' => $customerId,
            ],
            [
                'subtotal' => 100000,
                'total' => 100000,
                'notes' => 'Versi pertama',
            ],
            [
                [
                    'name' => 'Versi Pertama',
                    'pricing_method' => 'MANUAL',
                    'quantity' => 1,
                    'unit_price' => 100000,
                    'amount' => 100000,
                ],
            ]
        );

        $oldVersionId =
            $draft->current_version_id;

        $updated = $service->createRevision(
            $draft->id,
            [
                'subtotal' => 125000,
                'total' => 125000,
                'notes' => 'Versi kedua',
            ],
            [
                [
                    'name' => 'Versi Kedua',
                    'pricing_method' => 'MANUAL',
                    'quantity' => 1,
                    'unit_price' => 125000,
                    'amount' => 125000,
                ],
            ]
        );

        $this->assertNotSame(
            $oldVersionId,
            $updated->current_version_id
        );

        $this->assertSame(
            2,
            $updated->currentVersion->revision_no
        );

        $this->assertDatabaseHas(
            'quotation_versions',
            [
                'id' => $oldVersionId,
                'quotation_id' => $draft->id,
                'revision_no' => 1,
                'total' => 100000,
            ]
        );

        $this->assertDatabaseHas(
            'quotation_versions',
            [
                'id' =>
                    $updated->current_version_id,
                'quotation_id' => $draft->id,
                'revision_no' => 2,
                'total' => 125000,
            ]
        );

        $this->assertDatabaseCount(
            'quotation_versions',
            2
        );
    }


    public function test_send_transition_updates_status_timestamp_and_history(): void
    {
        $workspace = $this->workspace(
            'quotation-send@example.test',
            'Quotation Send'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-SEND'
        );

        $this->setTenantContext($workspace);

        $service = app(
            QuotationService::class
        );

        $quotation = $service->createDraft(
            [
                'quotation_number' => 'Q-SEND',
                'customer_id' => $customerId,
            ],
            [
                'notes' => 'Siap dikirim',
            ],
            [
                [
                    'name' => 'Item Send',
                    'pricing_method' => 'MANUAL',
                    'quantity' => 1,
                    'unit_price' => 100000,
                ],
            ]
        );

        $sent = $service->send(
            $quotation->id
        );

        $this->assertSame(
            'SENT',
            $sent->status
        );

        $this->assertNotNull(
            $sent->sent_at
        );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'quotation_id' =>
                    $quotation->id,
                'from_state' => 'DRAFT',
                'to_state' => 'SENT',
                'actor_user_id' =>
                    $workspace['user_id'],
                'source' => 'USER',
            ]
        );
    }

    public function test_send_from_non_draft_state_is_invalid_transition(): void
    {
        $workspace = $this->workspace(
            'quotation-send-invalid@example.test',
            'Quotation Send Invalid'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-SEND-INVALID'
        );

        $this->setTenantContext($workspace);

        $service = app(
            QuotationService::class
        );

        $quotation = $service->createDraft(
            [
                'quotation_number' =>
                    'Q-SEND-INVALID',
                'customer_id' =>
                    $customerId,
            ],
            [],
            [
                [
                    'name' => 'Item',
                    'pricing_method' => 'MANUAL',
                    'quantity' => 1,
                    'unit_price' => 100000,
                ],
            ]
        );

        DB::table('quotations')
            ->where('id', $quotation->id)
            ->update([
                'status' => 'SENT',
                'sent_at' => now(),
            ]);

        $this->expectException(
            \App\Exceptions\Quotation\InvalidQuotationTransitionException::class
        );

        $service->send(
            $quotation->id
        );
    }

    public function test_cancel_transition_sets_reason_timestamp_and_history(): void
    {
        $workspace = $this->workspace(
            'quotation-cancel@example.test',
            'Quotation Cancel'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-CANCEL'
        );

        $this->setTenantContext($workspace);

        $service = app(
            QuotationService::class
        );

        $quotation = $service->createDraft(
            [
                'quotation_number' =>
                    'Q-CANCEL',
                'customer_id' =>
                    $customerId,
            ],
            [],
            [
                [
                    'name' => 'Item Cancel',
                    'pricing_method' => 'MANUAL',
                    'quantity' => 1,
                    'unit_price' => 100000,
                ],
            ]
        );

        $cancelled = $service->cancel(
            $quotation->id,
            'Pelanggan membatalkan permintaan.'
        );

        $this->assertSame(
            'CANCELLED',
            $cancelled->status
        );

        $this->assertNotNull(
            $cancelled->cancelled_at
        );

        $this->assertDatabaseHas(
            'quotation_status_history',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'quotation_id' =>
                    $quotation->id,
                'from_state' => 'DRAFT',
                'to_state' => 'CANCELLED',
                'reason' =>
                    'Pelanggan membatalkan permintaan.',
            ]
        );
    }

    public function test_cancel_from_approved_state_is_invalid_transition(): void
    {
        $workspace = $this->workspace(
            'quotation-cancel-invalid@example.test',
            'Quotation Cancel Invalid'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-CANCEL-INVALID'
        );

        $this->setTenantContext($workspace);

        $service = app(
            QuotationService::class
        );

        $quotation = $service->createDraft(
            [
                'quotation_number' =>
                    'Q-CANCEL-INVALID',
                'customer_id' =>
                    $customerId,
            ],
            [],
            [
                [
                    'name' => 'Item',
                    'pricing_method' => 'MANUAL',
                    'quantity' => 1,
                    'unit_price' => 100000,
                ],
            ]
        );

        DB::table('quotations')
            ->where('id', $quotation->id)
            ->update([
                'status' => 'APPROVED',
                'approved_at' => now(),
            ]);

        $this->expectException(
            \App\Exceptions\Quotation\InvalidQuotationTransitionException::class
        );

        $service->cancel(
            $quotation->id,
            'Tidak valid.'
        );
    }


    public function test_invalid_transition_does_not_mutate_quotation_or_history(): void
    {
        $workspace = $this->workspace(
            'quotation-transition-atomic@example.test',
            'Quotation Transition Atomic'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-TRANSITION-ATOMIC'
        );

        $this->setTenantContext($workspace);

        $service = app(
            QuotationService::class
        );

        $quotation = $service->createDraft(
            [
                'quotation_number' =>
                    'Q-TRANSITION-ATOMIC',
                'customer_id' =>
                    $customerId,
            ],
            [],
            [
                [
                    'name' => 'Item',
                    'pricing_method' => 'MANUAL',
                    'quantity' => 1,
                    'unit_price' => 100000,
                ],
            ]
        );

        DB::table('quotations')
            ->where('id', $quotation->id)
            ->update([
                'status' => 'APPROVED',
                'approved_at' => now(),
            ]);

        $historyCountBefore = DB::table(
            'quotation_status_history'
        )
            ->where(
                'quotation_id',
                $quotation->id
            )
            ->count();

        try {
            $service->cancel(
                $quotation->id,
                'Tidak valid.'
            );

            $this->fail(
                'Expected invalid transition exception.'
            );
        } catch (
            \App\Exceptions\Quotation\InvalidQuotationTransitionException
        ) {
            // Expected.
        }

        $this->assertDatabaseHas(
            'quotations',
            [
                'id' => $quotation->id,
                'status' => 'APPROVED',
                'cancelled_at' => null,
            ]
        );

        $historyCountAfter = DB::table(
            'quotation_status_history'
        )
            ->where(
                'quotation_id',
                $quotation->id
            )
            ->count();

        $this->assertSame(
            $historyCountBefore,
            $historyCountAfter
        );
    }

    public function test_version_from_other_quotation_fails_application_invariant(): void
    {
        $workspace = $this->workspace(
            'quotation-invariant@example.test',
            'Quotation Invariant'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-INVARIANT'
        );

        $this->setTenantContext($workspace);

        $service = app(
            QuotationService::class
        );

        $first = $service->createDraft(
            [
                'quotation_number' => 'Q-INV-1',
                'customer_id' => $customerId,
            ],
            ['total' => 10000],
            [
                [
                    'name' => 'Item A',
                    'pricing_method' => 'MANUAL',
                    'amount' => 10000,
                ],
            ]
        );

        $second = $service->createDraft(
            [
                'quotation_number' => 'Q-INV-2',
                'customer_id' => $customerId,
            ],
            ['total' => 20000],
            [
                [
                    'name' => 'Item B',
                    'pricing_method' => 'MANUAL',
                    'amount' => 20000,
                ],
            ]
        );

        $foreignVersion =
            QuotationVersion::query()
                ->findOrFail(
                    $second->current_version_id
                );

        $this->expectException(
            RuntimeException::class
        );

        $service
            ->assertVersionBelongsToQuotation(
                $first,
                $foreignVersion
            );
    }

    public function test_empty_items_roll_back_entire_draft(): void
    {
        $workspace = $this->workspace(
            'quotation-empty@example.test',
            'Quotation Empty'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-EMPTY'
        );

        $this->setTenantContext($workspace);

        try {
            app(
                QuotationService::class
            )->createDraft(
                [
                    'quotation_number' =>
                        'Q-EMPTY',
                    'customer_id' =>
                        $customerId,
                ],
                [
                    'subtotal' => 0,
                    'total' => 0,
                ],
                []
            );

            $this->fail(
                'Expected InvalidArgumentException.'
            );
        } catch (InvalidArgumentException) {
            //
        }

        $this->assertDatabaseMissing(
            'quotations',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
                'quotation_number' =>
                    'Q-EMPTY',
            ]
        );
    }

    public function test_backend_calculates_totals_and_ignores_caller_supplied_totals(): void
    {
        $workspace = $this->workspace(
            'quotation-pricing-truth@example.test',
            'Quotation Pricing Truth'
        );

        $customerId = $this->insertCustomer(
            $workspace['tenant_id'],
            'CUST-PRICE-TRUTH'
        );

        $this->setTenantContext($workspace);

        $quotation = app(
            QuotationService::class
        )->createDraft(
            [
                'quotation_number' =>
                    'Q-PRICE-TRUTH',

                'customer_id' =>
                    $customerId,
            ],
            [
                // Nilai palsu dari caller harus diabaikan.
                'subtotal' => 1,
                'discount_total' => 1,
                'tax_total' => 1,
                'total' => 1,
            ],
            [
                [
                    'name' =>
                        'Spanduk Custom',

                    'item_type' =>
                        'SERVICE',

                    'pricing_method' =>
                        'AREA',

                    'pricing_config' => [
                        'width' => '3.5',
                        'height' => '1.2',
                    ],

                    'quantity' => '2',

                    'unit_price' =>
                        '25000',

                    // Nilai palsu.
                    'amount' => 1,
                    'tax_amount' => 1,
                ],
            ]
        );

        $version =
            $quotation->currentVersion;

        $item =
            $version->items->first();

        $this->assertSame(
            '210000.00',
            $version->subtotal
        );

        $this->assertSame(
            '0.00',
            $version->discount_total
        );

        $this->assertSame(
            '0.00',
            $version->tax_total
        );

        $this->assertSame(
            '210000.00',
            $version->total
        );

        $this->assertSame(
            '210000.00',
            $item->amount
        );
    }

    private function setTenantContext(
        array $workspace
    ): void {
        app(TenantContext::class)->set(
            $workspace['tenant_id'],
            $workspace['user_id']
        );

        app(BusinessContext::class)->set(
            $workspace['tenant_id'],
            $workspace['business_id'],
            $workspace['user_id']
        );
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' => 'Quotation Service Test',
            'email' => $email,
            'password' =>
                'SecurePassword123!',
            'tenant_name' => $tenantName,
        ]);
    }

    private function businessIdForTenant(
        string $tenantId
    ): string {
        $businessId = DB::table(
            'business_profiles'
        )
            ->where(
                'tenant_id',
                $tenantId
            )
            ->where(
                'is_default',
                true
            )
            ->where(
                'status',
                'ACTIVE'
            )
            ->value('id');

        if (!$businessId) {
            throw new \RuntimeException(
                'Active default business not found '
                . 'for tenant ' . $tenantId
            );
        }

        return (string) $businessId;
    }

    private function insertCustomer(
        string $tenantId,
        string $code
    ): string {
        $id = (string) Str::ulid();

        DB::table('customers')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'business_id' =>
                $this->businessIdForTenant(
                    $tenantId
                ),
            'type' => 'COMPANY',
            'code' => $code,
            'name' => $code,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function insertCatalogItem(
        string $tenantId,
        string $code,
        string $name,
        string $type,
        string $pricingMethod
    ): array {
        $id = (string) Str::ulid();

        $unitId = DB::table('units')
            ->where('tenant_id', $tenantId)
            ->where('code', 'M2')
            ->value('id');

        DB::table('catalog_items')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'business_id' =>
                $this->businessIdForTenant(
                    $tenantId
                ),
            'category_id' => null,
            'unit_id' => $unitId,
            'type' => $type,
            'code' => $code,
            'name' => $name,
            'description' => null,
            'pricing_method' => $pricingMethod,
            'base_price' => 75000,
            'currency' => 'IDR',
            'pricing_config' => null,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'unit_id' => $unitId,
        ];
    }
}
