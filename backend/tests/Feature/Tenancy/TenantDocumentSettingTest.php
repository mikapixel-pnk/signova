<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\BusinessProfile;
use App\Models\TenantDocumentSetting;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDocumentSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_business_can_have_one_document_setting_record(): void
    {
        $workspace = $this->workspace(
            'document-setting@example.test',
            'Reklame Nusantara'
        );

        TenantDocumentSetting::query()->create([
            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'business_name' =>
                'PT Reklame Nusantara',

            'address' =>
                'Jl. Contoh No. 10',

            'phone' =>
                '081234567890',

            'email' =>
                'halo@example.test',

            'tax_id' =>
                '01.234.567.8-999.000',

            'quotation_footer' =>
                'Terima kasih atas kepercayaan Anda.',
        ]);

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $workspace['business_id'],

                'business_name' =>
                    'PT Reklame Nusantara',
            ]
        );
    }

    public function test_database_rejects_duplicate_setting_for_same_business(): void
    {
        $workspace = $this->workspace(
            'document-setting-unique@example.test',
            'Unique Document Setting'
        );

        TenantDocumentSetting::query()->create([
            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'business_name' =>
                'Pertama',
        ]);

        $this->expectException(
            QueryException::class
        );

        TenantDocumentSetting::query()->create([
            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'business_name' =>
                'Kedua',
        ]);
    }

    public function test_same_tenant_can_have_separate_settings_for_two_businesses(): void
    {
        $workspace = $this->workspace(
            'document-setting-multi@example.test',
            'Multi Business Document Setting'
        );

        $secondBusiness =
            BusinessProfile::query()->create([
                'tenant_id' =>
                    $workspace['tenant_id'],

                'name' =>
                    'Usaha Kedua',

                'is_default' =>
                    false,

                'status' =>
                    'ACTIVE',
            ]);

        TenantDocumentSetting::query()->create([
            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'quotation_footer' =>
                'Footer usaha pertama',
        ]);

        TenantDocumentSetting::query()->create([
            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $secondBusiness->id,

            'quotation_footer' =>
                'Footer usaha kedua',
        ]);

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $workspace['business_id'],

                'quotation_footer' =>
                    'Footer usaha pertama',
            ]
        );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $secondBusiness->id,

                'quotation_footer' =>
                    'Footer usaha kedua',
            ]
        );

        $this->assertSame(
            2,
            TenantDocumentSetting::query()
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->count()
        );
    }

    public function test_setting_is_removed_when_tenant_is_deleted(): void
    {
        $workspace = $this->workspace(
            'document-setting-cascade@example.test',
            'Cascade Document Setting'
        );

        TenantDocumentSetting::query()->create([
            'tenant_id' =>
                $workspace['tenant_id'],

            'business_id' =>
                $workspace['business_id'],

            'business_name' =>
                'Cascade Test',
        ]);

        \DB::table('tenants')
            ->where(
                'id',
                $workspace['tenant_id']
            )
            ->delete();

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'business_id' =>
                    $workspace['business_id'],
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
                'Document Setting Test',

            'email' =>
                $email,

            'password' =>
                'SecurePassword123!',

            'tenant_name' =>
                $tenantName,
        ]);
    }
}
