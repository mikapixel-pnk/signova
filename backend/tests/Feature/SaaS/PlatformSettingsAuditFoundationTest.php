<?php

namespace Tests\Feature\SaaS;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformSettingsAuditFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_settings_and_audit_schema_exist(): void
    {
        $this->assertTrue(
            Schema::hasTable('platform_settings')
        );

        $this->assertTrue(
            Schema::hasTable('audit_logs')
        );

        $this->assertTrue(
            Schema::hasColumns('platform_settings', [
                'id',
                'namespace',
                'key',
                'value_type',
                'value',
                'schema_version',
                'description',
                'created_at',
                'updated_at',
            ])
        );

        $this->assertTrue(
            Schema::hasColumns('audit_logs', [
                'id',
                'actor_user_id',
                'tenant_id',
                'action',
                'entity_type',
                'entity_id',
                'source',
                'reason',
                'old_values',
                'new_values',
                'metadata',
                'ip_address',
                'user_agent',
                'request_id',
                'occurred_at',
                'created_at',
            ])
        );
    }

    public function test_platform_setting_is_unique_per_namespace_and_key(): void
    {
        $record = [
            'id' => (string) Str::ulid(),
            'namespace' => 'subscription',
            'key' => 'grace_policy',
            'value_type' => 'JSON',
            'value' => json_encode([
                'mode' => 'CONFIGURED_LATER',
            ]),
            'schema_version' => 1,
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('platform_settings')->insert($record);

        $record['id'] = (string) Str::ulid();

        $this->expectException(
            QueryException::class
        );

        DB::table('platform_settings')->insert($record);
    }

    public function test_platform_setting_rejects_unknown_value_type(): void
    {
        $this->expectException(
            QueryException::class
        );

        DB::table('platform_settings')->insert([
            'id' => (string) Str::ulid(),
            'namespace' => 'subscription',
            'key' => 'invalid_type_test',
            'value_type' => 'UNKNOWN',
            'value' => json_encode(true),
            'schema_version' => 1,
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_platform_setting_requires_positive_schema_version(): void
    {
        $this->expectException(
            QueryException::class
        );

        DB::table('platform_settings')->insert([
            'id' => (string) Str::ulid(),
            'namespace' => 'subscription',
            'key' => 'invalid_schema_test',
            'value_type' => 'BOOLEAN',
            'value' => json_encode(true),
            'schema_version' => 0,
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_audit_log_can_capture_sensitive_platform_change(): void
    {
        $auditId = (string) Str::ulid();
        $tenantId = (string) Str::ulid();
        $actorId = (string) Str::ulid();

        DB::table('audit_logs')->insert([
            'id' => $auditId,
            'actor_user_id' => $actorId,
            'tenant_id' => $tenantId,
            'action' => 'SUBSCRIPTION_DATES_ADJUSTED',
            'entity_type' => 'subscription',
            'entity_id' => (string) Str::ulid(),
            'source' => 'PLATFORM_ADMIN',
            'reason' => 'Audit foundation test',
            'old_values' => json_encode([
                'ends_at' => '2026-10-01T00:00:00+00:00',
            ]),
            'new_values' => json_encode([
                'ends_at' => '2026-11-01T00:00:00+00:00',
            ]),
            'metadata' => json_encode([
                'test' => true,
            ]),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SIGNOVA Test',
            'request_id' => 'audit-foundation-test',
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditId,
            'actor_user_id' => $actorId,
            'tenant_id' => $tenantId,
            'action' => 'SUBSCRIPTION_DATES_ADJUSTED',
            'source' => 'PLATFORM_ADMIN',
        ]);
    }

    public function test_audit_log_rejects_blank_identity_fields(): void
    {
        $this->expectException(
            QueryException::class
        );

        DB::table('audit_logs')->insert([
            'id' => (string) Str::ulid(),
            'actor_user_id' => null,
            'tenant_id' => null,
            'action' => '',
            'entity_type' => 'subscription',
            'entity_id' => null,
            'source' => 'SYSTEM',
            'reason' => null,
            'old_values' => null,
            'new_values' => null,
            'metadata' => null,
            'ip_address' => null,
            'user_agent' => null,
            'request_id' => null,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }
}
