<?php

namespace Tests\Feature\SaaS;

use Database\Seeders\SaasPlanFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaasPlanFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_saas_plan_foundation_schema_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('plans')
        );

        $this->assertTrue(
            Schema::hasTable('plan_versions')
        );

        $this->assertTrue(
            Schema::hasTable('plan_features')
        );

        $this->assertTrue(
            Schema::hasTable('plan_offerings')
        );

        $this->assertTrue(
            Schema::hasColumns('plans', [
                'id',
                'code',
                'name',
                'description',
                'is_active',
                'created_at',
                'updated_at',
            ])
        );

        $this->assertTrue(
            Schema::hasColumns('plan_versions', [
                'id',
                'plan_id',
                'version_no',
                'status',
                'effective_from',
                'effective_to',
                'created_at',
                'updated_at',
            ])
        );

        $this->assertTrue(
            Schema::hasColumns('plan_features', [
                'plan_version_id',
                'feature_id',
                'enabled',
                'default_limit',
                'default_value',
                'config',
                'config_schema_version',
                'created_at',
                'updated_at',
            ])
        );

        $this->assertTrue(
            Schema::hasColumns('plan_offerings', [
                'id',
                'plan_version_id',
                'code',
                'name',
                'billing_type',
                'duration_months',
                'duration_days',
                'price_amount',
                'currency',
                'is_active',
                'effective_from',
                'effective_to',
                'created_at',
                'updated_at',
            ])
        );

        $this->assertTrue(
            Schema::hasTable('features')
        );
    }

    public function test_canonical_plans_can_be_seeded_idempotently(): void
    {
        $this->seed(
            SaasPlanFoundationSeeder::class
        );

        $this->assertDatabaseHas('plans', [
            'code' => 'STARTER',
            'name' => 'Starter',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('plans', [
            'code' => 'BUSINESS',
            'name' => 'Bisnis',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('plans', [
            'code' => 'PRO',
            'name' => 'Pro',
            'is_active' => true,
        ]);

        $this->seed(
            SaasPlanFoundationSeeder::class
        );

        $this->assertSame(
            3,
            DB::table('plans')->count()
        );
    }
}
