<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');

            $table->string(
                'invoice_number',
                100
            );

            $table->ulid('customer_id');

            $table->ulid(
                'project_id'
            )->nullable();

            $table->ulid(
                'source_quotation_id'
            )->nullable();

            $table->ulid(
                'source_quotation_version_id'
            )->nullable();

            $table->string(
                'status',
                32
            )->default('DRAFT');

            $table->timestampTz(
                'issued_at'
            )->nullable();

            $table->timestampTz(
                'due_at'
            )->nullable();

            $table->string(
                'currency',
                3
            )->default('IDR');

            $table->decimal(
                'subtotal',
                18,
                2
            )->default(0);

            $table->decimal(
                'discount_total',
                18,
                2
            )->default(0);

            $table->decimal(
                'tax_total',
                18,
                2
            )->default(0);

            $table->decimal(
                'total',
                18,
                2
            )->default(0);

            $table->text(
                'notes'
            )->nullable();

            $table->ulid(
                'created_by_user_id'
            )->nullable();

            $table->timestampsTz();

            $table->unique([
                'tenant_id',
                'invoice_number',
            ]);

            $table->unique([
                'tenant_id',
                'source_quotation_version_id',
            ]);

            $table->unique([
                'id',
                'tenant_id',
            ]);

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign([
                'customer_id',
                'tenant_id',
            ])
                ->references([
                    'id',
                    'tenant_id',
                ])
                ->on('customers');

            $table->foreign([
                'source_quotation_id',
                'tenant_id',
            ])
                ->references([
                    'id',
                    'tenant_id',
                ])
                ->on('quotations');

            $table->foreign([
                'source_quotation_version_id',
                'tenant_id',
            ])
                ->references([
                    'id',
                    'tenant_id',
                ])
                ->on('quotation_versions');

            $table->foreign([
                'tenant_id',
                'created_by_user_id',
            ])
                ->references([
                    'tenant_id',
                    'user_id',
                ])
                ->on('tenant_users');

            $table->index([
                'tenant_id',
                'status',
            ]);

            $table->index([
                'tenant_id',
                'customer_id',
            ]);

            $table->index([
                'tenant_id',
                'due_at',
            ]);
        });

        Schema::create(
            'invoice_items',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('invoice_id');

                $table->ulid(
                    'source_quotation_item_id'
                )->nullable();

                $table->ulid(
                    'catalog_item_id'
                )->nullable();

                $table->string(
                    'item_type',
                    32
                )->nullable();

                $table->string(
                    'code',
                    100
                )->nullable();

                $table->string(
                    'name',
                    190
                );

                $table->text(
                    'description'
                )->nullable();

                $table->decimal(
                    'quantity',
                    18,
                    4
                );

                $table->string(
                    'unit_code',
                    64
                )->nullable();

                $table->string(
                    'unit_name',
                    100
                )->nullable();

                $table->string(
                    'unit_symbol',
                    32
                )->nullable();

                $table->decimal(
                    'unit_price',
                    18,
                    2
                );

                $table->decimal(
                    'discount_amount',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'tax_amount',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'amount',
                    18,
                    2
                );

                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->timestampsTz();

                $table->unique([
                    'id',
                    'tenant_id',
                ]);

                $table->foreign([
                    'invoice_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('invoices')
                    ->cascadeOnDelete();

                $table->foreign([
                    'catalog_item_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('catalog_items');

                $table->index([
                    'tenant_id',
                    'invoice_id',
                    'sort_order',
                ]);
            }
        );

        Schema::create(
            'invoice_status_history',
            function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('tenant_id');
                $table->ulid('invoice_id');

                $table->string(
                    'from_state',
                    32
                )->nullable();

                $table->string(
                    'to_state',
                    32
                );

                $table->ulid(
                    'actor_user_id'
                )->nullable();

                $table->text(
                    'reason'
                )->nullable();

                $table->string(
                    'source',
                    32
                )->default('USER');

                $table->jsonb(
                    'context'
                )->nullable();

                $table->timestampTz(
                    'occurred_at'
                );

                $table->timestampsTz();

                $table->foreign([
                    'invoice_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('invoices')
                    ->cascadeOnDelete();

                $table->foreign([
                    'tenant_id',
                    'actor_user_id',
                ])
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->index([
                    'tenant_id',
                    'invoice_id',
                    'occurred_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'invoice_status_history'
        );

        Schema::dropIfExists(
            'invoice_items'
        );

        Schema::dropIfExists(
            'invoices'
        );
    }
};
