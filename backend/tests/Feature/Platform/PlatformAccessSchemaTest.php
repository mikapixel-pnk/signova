<?php

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformAccessSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_access_tables_exist(): void
    {
        $this->assertTrue(
            Schema::hasTable(
                'platform_capabilities'
            )
        );

        $this->assertTrue(
            Schema::hasTable(
                'platform_roles'
            )
        );

        $this->assertTrue(
            Schema::hasTable(
                'platform_role_capabilities'
            )
        );

        $this->assertTrue(
            Schema::hasTable(
                'platform_user_roles'
            )
        );
    }

    public function test_platform_access_columns_exist(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'platform_capabilities',
                [
                    'id',
                    'code',
                    'name',
                    'description',
                    'is_sensitive',
                    'is_active',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'platform_roles',
                [
                    'id',
                    'code',
                    'name',
                    'description',
                    'status',
                    'is_system',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'platform_role_capabilities',
                [
                    'platform_role_id',
                    'platform_capability_id',
                    'effect',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'platform_user_roles',
                [
                    'user_id',
                    'platform_role_id',
                    'assigned_at',
                    'assigned_by_user_id',
                ]
            )
        );
    }
}
