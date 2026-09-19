<?php

namespace Tests\Feature\SaaS;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantLifecycleRetentionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_lifecycle_retention_schema_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('tenant_status_history')
        );

        $this->assertTrue(
            Schema::hasTable('tenant_retention_controls')
        );

        $this->assertTrue(
            Schema::hasColumns('tenant_status_history', [
                'id',
                'tenant_id',
                'from_status',
                'to_status',
                'source',
                'reason',
                'actor_user_id',
                'metadata',
                'occurred_at',
                'created_at',
            ])
        );

        $this->assertTrue(
            Schema::hasColumns('tenant_retention_controls', [
                'tenant_id',
                'delete_requested_at',
                'delete_requested_by_user_id',
                'delete_request_source',
                'delete_request_reason',
                'retention_until',
                'purge_approved_at',
                'purge_approved_by_user_id',
                'purge_approval_source',
                'created_at',
                'updated_at',
            ])
        );
    }

    public function test_status_history_rejects_noop_transition(): void
    {
        $tenantId = $this->createTenant();

        $this->expectException(
            QueryException::class
        );

        DB::table('tenant_status_history')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'from_status' => 'ACTIVE',
            'to_status' => 'ACTIVE',
            'source' => 'TEST',
            'reason' => 'No-op transition',
            'actor_user_id' => null,
            'metadata' => null,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_delete_request_requires_source_and_reason(): void
    {
        $tenantId = $this->createTenant();

        $this->expectException(
            QueryException::class
        );

        DB::table('tenant_retention_controls')->insert([
            'tenant_id' => $tenantId,
            'delete_requested_at' => now(),
            'delete_requested_by_user_id' => null,
            'delete_request_source' => null,
            'delete_request_reason' => null,
            'retention_until' => null,
            'purge_approved_at' => null,
            'purge_approved_by_user_id' => null,
            'purge_approval_source' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_retention_until_cannot_precede_delete_request(): void
    {
        $tenantId = $this->createTenant();
        $requestedAt = now();

        $this->expectException(
            QueryException::class
        );

        DB::table('tenant_retention_controls')->insert([
            'tenant_id' => $tenantId,
            'delete_requested_at' => $requestedAt,
            'delete_requested_by_user_id' => null,
            'delete_request_source' => 'PLATFORM_ADMIN',
            'delete_request_reason' => 'Retention validation test',
            'retention_until' => $requestedAt->copy()->subDay(),
            'purge_approved_at' => null,
            'purge_approved_by_user_id' => null,
            'purge_approval_source' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_purge_approval_requires_resolved_retention_window(): void
    {
        $tenantId = $this->createTenant();

        $this->expectException(
            QueryException::class
        );

        DB::table('tenant_retention_controls')->insert([
            'tenant_id' => $tenantId,
            'delete_requested_at' => now(),
            'delete_requested_by_user_id' => null,
            'delete_request_source' => 'PLATFORM_ADMIN',
            'delete_request_reason' => 'Purge approval test',
            'retention_until' => null,
            'purge_approved_at' => now(),
            'purge_approved_by_user_id' => null,
            'purge_approval_source' => 'PLATFORM_ADMIN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_only_one_retention_control_exists_per_tenant(): void
    {
        $tenantId = $this->createTenant();

        $record = [
            'tenant_id' => $tenantId,
            'delete_requested_at' => null,
            'delete_requested_by_user_id' => null,
            'delete_request_source' => null,
            'delete_request_reason' => null,
            'retention_until' => null,
            'purge_approved_at' => null,
            'purge_approved_by_user_id' => null,
            'purge_approval_source' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('tenant_retention_controls')
            ->insert($record);

        $this->expectException(
            QueryException::class
        );

        DB::table('tenant_retention_controls')
            ->insert($record);
    }

    public function test_history_blocks_uncontrolled_tenant_hard_delete(): void
    {
        $tenantId = $this->createTenant();

        DB::table('tenant_status_history')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'from_status' => null,
            'to_status' => 'ACTIVE',
            'source' => 'TEST',
            'reason' => 'Initial lifecycle state',
            'actor_user_id' => null,
            'metadata' => null,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table('tenants')
            ->where('id', $tenantId)
            ->delete();
    }

    private function createTenant(): string
    {
        $tenantId = (string) Str::ulid();
        $now = now();

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Tenant Lifecycle Test',
            'code' => null,
            'slug' => 'tenant-lifecycle-' . strtolower(
                Str::random(12)
            ),
            'lifecycle_status' => 'ACTIVE',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'primary_owner_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $tenantId;
    }
}
