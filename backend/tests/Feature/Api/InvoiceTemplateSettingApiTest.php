<?php

namespace Tests\Feature\Api;

use App\Actions\Tenancy\CreateTenantWorkspaceAction;
use App\Models\User;
use Database\Seeders\SignovaAccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceTemplateSettingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            SignovaAccessControlSeeder::class
        );
    }

    public function test_get_returns_virtual_default_without_creating_setting_row(): void
    {
        $workspace =
            $this->workspace(
                'template-default@example.test',
                'Template Default'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );

        $this->getJson(
            '/api/v1/settings/invoice-templates'
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.selected.template_key',
                'classic_blue'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'blue'
            );

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );
    }

    public function test_catalog_contains_starter_and_premium_templates(): void
    {
        $workspace =
            $this->workspace(
                'template-catalog@example.test',
                'Template Catalog'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $response =
            $this->getJson(
                '/api/v1/settings/invoice-templates'
            )
                ->assertOk();

        $templates =
            $response->json(
                'data.templates'
            );

        $this->assertCount(
            4,
            $templates
        );

        $this->assertSame(
            [
                'classic_blue',
                'modern_emerald',
                'minimal_slate',
                'premium_navy',
            ],
            array_column(
                $templates,
                'key'
            )
        );

        foreach (
            array_slice(
                $templates,
                0,
                3
            ) as $template
        ) {
            $this->assertSame(
                'STARTER',
                $template['tier']
            );

            $this->assertTrue(
                $template['is_available']
            );
        }

        $premium =
            $templates[3];

        $this->assertSame(
            'Premium Navy',
            $premium['name']
        );

        $this->assertSame(
            'PREMIUM',
            $premium['tier']
        );

        $this->assertFalse(
            $premium['is_available']
        );

        $this->assertArrayNotHasKey(
            'author',
            $premium
        );
    }

    public function test_owner_can_select_template_and_palette(): void
    {
        $workspace =
            $this->workspace(
                'template-update@example.test',
                'Template Update'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'modern_emerald',

                'invoice_palette_key' =>
                    'emerald',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.selected.template_key',
                'modern_emerald'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'emerald'
            );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'invoice_template_key' =>
                    'modern_emerald',

                'invoice_palette_key' =>
                    'emerald',
            ]
        );
    }

    public function test_palette_is_optional_and_uses_template_default(): void
    {
        $workspace =
            $this->workspace(
                'template-default-palette@example.test',
                'Template Default Palette'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'minimal_slate',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.selected.template_key',
                'minimal_slate'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'slate'
            );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],

                'invoice_template_key' =>
                    'minimal_slate',

                'invoice_palette_key' =>
                    'slate',
            ]
        );
    }

    public function test_unsupported_palette_is_rejected(): void
    {
        $workspace =
            $this->workspace(
                'template-invalid-palette@example.test',
                'Template Invalid Palette'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'classic_blue',

                'invoice_palette_key' =>
                    'emerald',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );
    }

    public function test_unknown_template_is_rejected(): void
    {
        $workspace =
            $this->workspace(
                'template-invalid@example.test',
                'Template Invalid'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'not_a_template',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_settings_view_capability_is_required_for_catalog(): void
    {
        $workspace =
            $this->workspace(
                'template-view-denied@example.test',
                'Template View Denied'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'settings.view'
        );

        $this->getJson(
            '/api/v1/settings/invoice-templates'
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_settings_manage_capability_is_required_for_update(): void
    {
        $workspace =
            $this->workspace(
                'template-manage-denied@example.test',
                'Template Manage Denied'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'settings.manage'
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'modern_emerald',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'FORBIDDEN_CAPABILITY'
            );
    }

    public function test_template_selection_is_tenant_scoped(): void
    {
        $first =
            $this->workspace(
                'template-first@example.test',
                'Template First'
            );

        $second =
            $this->workspace(
                'template-second@example.test',
                'Template Second'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'modern_emerald',
            ]
        )->assertOk();

        $this->actingAsWorkspace(
            $second
        );

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'minimal_slate',
            ]
        )->assertOk();

        $this->getJson(
            '/api/v1/settings/invoice-templates'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.selected.template_key',
                'minimal_slate'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'slate'
            );

        $this->actingAsWorkspace(
            $first
        );

        $this->getJson(
            '/api/v1/settings/invoice-templates'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.selected.template_key',
                'modern_emerald'
            )
            ->assertJsonPath(
                'data.selected.palette_key',
                'emerald'
            );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $first['tenant_id'],

                'invoice_template_key' =>
                    'modern_emerald',
            ]
        );

        $this->assertDatabaseHas(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $second['tenant_id'],

                'invoice_template_key' =>
                    'minimal_slate',
            ]
        );
    }

    public function test_owner_can_preview_all_catalog_templates(): void
    {
        $workspace =
            $this->workspace(
                'template-preview@example.test',
                'Template Preview'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $cases = [
            'classic_blue' =>
                'classic',

            'modern_emerald' =>
                'modern',

            'minimal_slate' =>
                'minimal',

            'premium_navy' =>
                'premium',
        ];

        foreach (
            $cases
            as $templateKey =>
                $layout
        ) {
            $response =
                $this->get(
                    "/api/v1/settings/invoice-templates/{$templateKey}/preview"
                )
                    ->assertOk()
                    ->assertHeader(
                        'Content-Type',
                        'text/html; charset=UTF-8'
                    );

            $this->assertStringContainsString(
                'data-invoice-layout="'
                . $layout
                . '"',
                $response->getContent()
            );

            $this->assertStringContainsString(
                'INV-202609-0001',
                $response->getContent()
            );
        }
    }

    public function test_preview_does_not_create_document_setting_row(): void
    {
        $workspace =
            $this->workspace(
                'template-preview-readonly@example.test',
                'Template Preview Readonly'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->get(
            '/api/v1/settings/invoice-templates/classic_blue/preview'
        )->assertOk();

        $this->assertDatabaseMissing(
            'tenant_document_settings',
            [
                'tenant_id' =>
                    $workspace['tenant_id'],
            ]
        );
    }

    public function test_preview_unknown_template_returns_not_found(): void
    {
        $workspace =
            $this->workspace(
                'template-preview-missing@example.test',
                'Template Preview Missing'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->get(
            '/api/v1/settings/invoice-templates/not_a_template/preview'
        )->assertNotFound();
    }

    public function test_preview_rejects_unsupported_palette(): void
    {
        $workspace =
            $this->workspace(
                'template-preview-palette@example.test',
                'Template Preview Palette'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/settings/invoice-templates/classic_blue/preview?palette=emerald'
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }

    public function test_settings_view_capability_is_required_for_preview(): void
    {
        $workspace =
            $this->workspace(
                'template-preview-denied@example.test',
                'Template Preview Denied'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->denyCapability(
            $workspace,
            'settings.view'
        );

        $this->get(
            '/api/v1/settings/invoice-templates/classic_blue/preview'
        )
            ->assertForbidden();
    }


    public function test_premium_template_is_visible_but_not_selectable_on_starter(): void
    {
        $workspace =
            $this->workspace(
                'template-premium-locked@example.test',
                'Template Premium Locked'
            );

        $this->actingAsWorkspace(
            $workspace
        );

        $this->getJson(
            '/api/v1/settings/invoice-templates'
        )
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'premium_navy',
                'name' => 'Premium Navy',
                'tier' => 'PREMIUM',
                'is_available' => false,
            ]);

        $this->patchJson(
            '/api/v1/settings/invoice-templates',
            [
                'invoice_template_key' =>
                    'premium_navy',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'VALIDATION_FAILED'
            );
    }


    private function workspace(
        string $email,
        string $businessName
    ): array {
        $workspace =
            app(
                CreateTenantWorkspaceAction::class
            )->execute([
                'name' =>
                    'Owner',

                'email' =>
                    $email,

                'password' =>
                    'password',

                'tenant_name' =>
                    $businessName,

                'timezone' =>
                    'Asia/Jakarta',
            ]);

        return [
            'user_id' =>
                $workspace['user_id'],

            'tenant_id' =>
                $workspace['tenant_id'],

            'user' =>
                User::query()->findOrFail(
                    $workspace['user_id']
                ),
        ];
    }

    private function actingAsWorkspace(
        array $workspace
    ): void {
        $this->actingAs(
            $workspace['user'],
            'sanctum'
        );

        $this->withHeader(
            'X-Tenant-ID',
            $workspace['tenant_id']
        );
    }

    private function denyCapability(
        array $workspace,
        string $capability
    ): void {
        $roleId =
            DB::table('roles')
                ->where(
                    'tenant_id',
                    $workspace['tenant_id']
                )
                ->where(
                    'code',
                    'OWNER'
                )
                ->value('id');

        $capabilityId =
            DB::table('capabilities')
                ->where(
                    'code',
                    $capability
                )
                ->value('id');

        $this->assertNotNull(
            $roleId
        );

        $this->assertNotNull(
            $capabilityId
        );

        DB::table(
            'role_capabilities'
        )->updateOrInsert(
            [
                'role_id' =>
                    $roleId,

                'capability_id' =>
                    $capabilityId,
            ],
            [
                'effect' =>
                    'DENY',

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]
        );
    }
}
