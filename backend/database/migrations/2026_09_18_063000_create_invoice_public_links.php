<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'invoice_public_links',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');
                $table->ulid('business_id');
                $table->ulid('invoice_id');

                /*
                 * Raw token tidak pernah disimpan.
                 * Database hanya menyimpan SHA-256 hex digest.
                 */
                $table->char(
                    'token_hash',
                    64
                );

                $table->timestampTz(
                    'expires_at'
                )->nullable();

                $table->timestampTz(
                    'revoked_at'
                )->nullable();

                $table->ulid(
                    'created_by_user_id'
                )->nullable();

                $table->timestampsTz();

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                /*
                 * Business harus benar-benar milik Tenant
                 * yang sama.
                 */
                $table->foreign(
                    [
                        'business_id',
                        'tenant_id',
                    ],
                    'inv_pub_links_business_tenant_fk'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles')
                    ->cascadeOnDelete();

                /*
                 * Public link harus menunjuk Invoice dari
                 * Tenant + Business yang sama.
                 *
                 * invoices sudah memiliki candidate key
                 * (id, business_id, tenant_id) karena
                 * payment_allocations juga mereferensikan
                 * kombinasi tersebut.
                 */
                $table->foreign(
                    [
                        'invoice_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'inv_pub_links_invoice_business_tenant_fk'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('invoices')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'tenant_id',
                        'created_by_user_id',
                    ],
                    'inv_pub_links_creator_tenant_user_fk'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->unique(
                    'token_hash',
                    'invoice_public_links_token_hash_unique'
                );

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'invoice_public_links_id_business_tenant_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'invoice_id',
                    ],
                    'invoice_public_links_invoice_index'
                );

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'invoice_id',
                        'revoked_at',
                    ],
                    'invoice_public_links_active_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'invoice_public_links'
        );
    }
};
