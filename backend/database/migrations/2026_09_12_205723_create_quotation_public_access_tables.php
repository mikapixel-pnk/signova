<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'quotation_public_links',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('quotation_id');
                $table->ulid('quotation_version_id');

                /*
                 * Raw public token tidak disimpan.
                 * Simpan SHA-256 hex digest saja.
                 */
                $table->char('token_hash', 64);

                $table->timestampTz('expires_at')
                    ->nullable();

                $table->timestampTz('revoked_at')
                    ->nullable();

                $table->ulid('created_by_user_id')
                    ->nullable();

                $table->timestampsTz();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['quotation_id', 'tenant_id'],
                    'quotation_public_links_quotation_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('quotations')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['quotation_version_id', 'tenant_id'],
                    'quotation_public_links_version_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('quotation_versions')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['tenant_id', 'created_by_user_id'],
                    'quotation_public_links_creator_tenant_user_foreign'
                )
                    ->references(['tenant_id', 'user_id'])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->unique(
                    'token_hash',
                    'quotation_public_links_token_hash_unique'
                );

                $table->unique(
                    ['id', 'tenant_id'],
                    'quotation_public_links_id_tenant_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'quotation_id',
                        'quotation_version_id',
                    ],
                    'quotation_public_links_quote_version_index'
                );

                $table->index(
                    [
                        'tenant_id',
                        'quotation_id',
                        'revoked_at',
                    ],
                    'quotation_public_links_active_index'
                );
            }
        );

        Schema::create(
            'quotation_actions',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('quotation_id');
                $table->ulid('quotation_version_id');

                /*
                 * Nullable agar tabel quotation_actions tetap bisa
                 * dipakai untuk internal USER/SYSTEM action kelak.
                 */
                $table->ulid('public_link_id')
                    ->nullable();

                $table->string('action', 32);

                $table->string('actor_type', 32)
                    ->default('PUBLIC');

                $table->ulid('actor_user_id')
                    ->nullable();

                $table->text('note')->nullable();

                $table->jsonb('context')->nullable();

                $table->timestampTz('occurred_at')
                    ->useCurrent();

                $table->timestampsTz();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['quotation_id', 'tenant_id'],
                    'quotation_actions_quotation_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('quotations')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['quotation_version_id', 'tenant_id'],
                    'quotation_actions_version_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('quotation_versions')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['public_link_id', 'tenant_id'],
                    'quotation_actions_link_tenant_foreign'
                )
                    ->references(['id', 'tenant_id'])
                    ->on('quotation_public_links')
                    ->cascadeOnDelete();

                $table->foreign(
                    ['tenant_id', 'actor_user_id'],
                    'quotation_actions_actor_tenant_user_foreign'
                )
                    ->references(['tenant_id', 'user_id'])
                    ->on('tenant_users')
                    ->restrictOnDelete();

                $table->unique(
                    ['id', 'tenant_id'],
                    'quotation_actions_id_tenant_unique'
                );

                /*
                 * Untuk public link:
                 * satu VIEW, satu APPROVE, satu REJECT maksimum
                 * per link. State machine tetap mencegah
                 * APPROVE + REJECT keduanya valid.
                 *
                 * PostgreSQL membolehkan banyak NULL pada unique,
                 * sehingga internal action tanpa public_link_id
                 * tidak terganggu.
                 */
                $table->unique(
                    [
                        'public_link_id',
                        'quotation_version_id',
                        'action',
                    ],
                    'quotation_actions_link_version_action_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'quotation_id',
                        'occurred_at',
                    ],
                    'quotation_actions_tenant_quote_time_index'
                );

                $table->index(
                    [
                        'tenant_id',
                        'quotation_version_id',
                        'action',
                    ],
                    'quotation_actions_tenant_version_action_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_actions');
        Schema::dropIfExists('quotation_public_links');
    }
};
