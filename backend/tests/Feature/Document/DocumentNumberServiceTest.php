<?php

namespace Tests\Feature\Document;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use App\Services\Document\DocumentNumberService;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-14 12:00:00',
                'Asia/Jakarta'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_invoice_number_starts_at_one_and_increments(): void
    {
        $workspace =
            $this->workspace(
                'sequence-a@example.test',
                'Sequence A'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $service =
            app(
                DocumentNumberService::class
            );

        $this->assertSame(
            'INV-202609-0001',
            $service->nextInvoiceNumber()
        );

        $this->assertSame(
            'INV-202609-0002',
            $service->nextInvoiceNumber()
        );

        $this->assertDatabaseHas(
            'tenant_sequences',
            [
                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'document_type' =>
                    'INVOICE',

                'period' =>
                    '202609',

                'prefix' =>
                    'INV-202609-',

                'next_number' =>
                    3,

                'padding' =>
                    4,
            ]
        );
    }

    public function test_each_tenant_has_independent_invoice_sequence(): void
    {
        $first =
            $this->workspace(
                'sequence-first@example.test',
                'Sequence First'
            );

        $second =
            $this->workspace(
                'sequence-second@example.test',
                'Sequence Second'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->assertSame(
            'INV-202609-0001',
            app(
                DocumentNumberService::class
            )->nextInvoiceNumber()
        );

        $this->actingAsWorkspace(
            $second
        );

        $this->assertSame(
            'INV-202609-0001',
            app(
                DocumentNumberService::class
            )->nextInvoiceNumber()
        );

        $this->assertSame(
            2,
            DB::table(
                'tenant_sequences'
            )
                ->where(
                    'document_type',
                    'INVOICE'
                )
                ->count()
        );
    }

    public function test_new_period_starts_new_sequence(): void
    {
        $workspace =
            $this->workspace(
                'sequence-period@example.test',
                'Sequence Period'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $service =
            app(
                DocumentNumberService::class
            );

        $this->assertSame(
            'INV-202609-0001',
            $service->nextInvoiceNumber()
        );

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-10-01 08:00:00',
                'Asia/Jakarta'
            )
        );

        $this->assertSame(
            'INV-202610-0001',
            $service->nextInvoiceNumber()
        );

        $this->assertSame(
            2,
            DB::table(
                'tenant_sequences'
            )
                ->where(
                    'tenant_id',
                    $workspace[
                        'tenant_id'
                    ]
                )
                ->where(
                    'document_type',
                    'INVOICE'
                )
                ->count()
        );
    }

    public function test_sequence_is_rolled_back_when_parent_transaction_fails(): void
    {
        $workspace =
            $this->workspace(
                'sequence-rollback@example.test',
                'Sequence Rollback'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $service =
            app(
                DocumentNumberService::class
            );

        try {
            DB::transaction(
                function () use (
                    $service
                ): void {
                    $this->assertSame(
                        'INV-202609-0001',
                        $service->nextInvoiceNumber()
                    );

                    throw new \RuntimeException(
                        'Force parent rollback.'
                    );
                }
            );

            $this->fail(
                'Parent transaction should have failed.'
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Force parent rollback.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing(
            'tenant_sequences',
            [
                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'document_type' =>
                    'INVOICE',

                'period' =>
                    '202609',
            ]
        );

        $this->assertSame(
            'INV-202609-0001',
            $service->nextInvoiceNumber()
        );

        $this->assertDatabaseHas(
            'tenant_sequences',
            [
                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'document_type' =>
                    'INVOICE',

                'period' =>
                    '202609',

                'next_number' =>
                    2,
            ]
        );
    }

    public function test_invoice_period_uses_tenant_timezone(): void
    {
        $workspace =
            $this->workspace(
                'sequence-timezone@example.test',
                'Sequence Timezone'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-09-30 17:30:00',
                'UTC'
            )
        );

        $this->assertSame(
            'INV-202610-0001',
            app(
                DocumentNumberService::class
            )->nextInvoiceNumber()
        );

        $this->assertDatabaseHas(
            'tenant_sequences',
            [
                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'document_type' =>
                    'INVOICE',

                'period' =>
                    '202610',
            ]
        );
    }

    public function test_existing_sequence_counter_and_padding_are_respected(): void
    {
        $workspace =
            $this->workspace(
                'sequence-config@example.test',
                'Sequence Config'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        DB::table(
            'tenant_sequences'
        )->insert([
            'id' =>
                (string)
                \Illuminate\Support\Str::ulid(),

            'tenant_id' =>
                $workspace[
                    'tenant_id'
                ],

            'business_id' =>
                $workspace[
                    'business_id'
                ],

            'document_type' =>
                'INVOICE',

            'period' =>
                '202609',

            'prefix' =>
                'TAG-26-',

            'next_number' =>
                17,

            'padding' =>
                6,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $number =
            app(
                DocumentNumberService::class
            )->nextInvoiceNumber();

        $this->assertSame(
            'INV-202609-000017',
            $number
        );

        $this->assertDatabaseHas(
            'tenant_sequences',
            [
                'tenant_id' =>
                    $workspace[
                        'tenant_id'
                    ],

                'document_type' =>
                    'INVOICE',

                'period' =>
                    '202609',

                'next_number' =>
                    18,
            ]
        );
    }

    private function workspace(
        string $email,
        string $tenantName
    ): array {
        return app(
            CreateTenantWorkspaceAction::class
        )->execute([
            'name' =>
                'Sequence Owner',

            'email' =>
                $email,

            'password' =>
                'password',

            'tenant_name' =>
                $tenantName,

            'timezone' =>
                'Asia/Jakarta',
        ]);
    }

    private function actingAsWorkspace(
        array $workspace
    ): void {
        $user =
            User::query()
                ->findOrFail(
                    $workspace['user_id']
                );

        $this->actingAs(
            $user,
            'sanctum'
        );

        $this->withHeader(
            'X-Tenant-ID',
            $workspace['tenant_id']
        );

        app(
            TenantContext::class
        )->set(
            $workspace['tenant_id'],
            $workspace['user_id']
        );

        app(
            BusinessContext::class
        )->set(
            $workspace['tenant_id'],
            $workspace['business_id'],
            $workspace['user_id']
        );
    }
}
