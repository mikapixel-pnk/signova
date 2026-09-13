<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'tenant_payment_settings',
            function (Blueprint $table): void {
                $table->ulid('tenant_id')
                    ->primary();

                $table->boolean(
                    'bank_transfer_enabled'
                )->default(false);

                $table->string(
                    'bank_name',
                    100
                )->nullable();

                $table->string(
                    'bank_account_number',
                    100
                )->nullable();

                $table->string(
                    'bank_account_name',
                    190
                )->nullable();

                $table->boolean(
                    'static_qr_enabled'
                )->default(false);

                $table->ulid(
                    'static_qr_file_id'
                )->nullable();

                $table->boolean(
                    'midtrans_enabled'
                )->default(false);

                $table->boolean(
                    'partial_payment_enabled'
                )->default(true);

                $table->timestampsTz();

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign([
                    'static_qr_file_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('files');
            }
        );

        Schema::create(
            'payment_provider_accounts',
            function (Blueprint $table): void {
                $table->ulid('id')
                    ->primary();

                $table->ulid(
                    'tenant_id'
                );

                $table->string(
                    'provider',
                    32
                );

                $table->string(
                    'environment',
                    16
                )->default('SANDBOX');

                $table->string(
                    'merchant_id',
                    190
                )->nullable();

                /*
                 * Nilai kolom ini HARUS encrypted
                 * oleh application layer.
                 * Jangan pernah return raw secret
                 * lewat API/resource/log.
                 */
                $table->text(
                    'client_key_encrypted'
                )->nullable();

                $table->text(
                    'server_key_encrypted'
                )->nullable();

                $table->string(
                    'connection_status',
                    32
                )->default('DISCONNECTED');

                $table->timestampTz(
                    'last_verified_at'
                )->nullable();

                $table->timestampTz(
                    'last_success_at'
                )->nullable();

                $table->timestampsTz();

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->unique([
                    'id',
                    'tenant_id',
                ]);

                $table->unique([
                    'tenant_id',
                    'provider',
                ]);

                $table->index([
                    'tenant_id',
                    'connection_status',
                ]);
            }
        );

        Schema::create(
            'payments',
            function (Blueprint $table): void {
                $table->ulid('id')
                    ->primary();

                $table->ulid(
                    'tenant_id'
                );

                $table->ulid(
                    'customer_id'
                );

                $table->decimal(
                    'amount',
                    18,
                    2
                );

                $table->string(
                    'currency',
                    3
                )->default('IDR');

                $table->timestampTz(
                    'paid_at'
                )->nullable();

                $table->string(
                    'method',
                    32
                );

                $table->string(
                    'status',
                    32
                )->default('PENDING');

                $table->string(
                    'reference',
                    190
                )->nullable();

                $table->ulid(
                    'evidence_file_id'
                )->nullable();

                $table->string(
                    'provider',
                    32
                )->nullable();

                $table->string(
                    'provider_reference',
                    190
                )->nullable();

                $table->string(
                    'provider_transaction_id',
                    190
                )->nullable();

                $table->string(
                    'idempotency_key',
                    190
                )->nullable();

                $table->ulid(
                    'created_by_user_id'
                )->nullable();

                $table->ulid(
                    'verified_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'verified_at'
                )->nullable();

                $table->ulid(
                    'rejected_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'rejected_at'
                )->nullable();

                $table->text(
                    'rejection_reason'
                )->nullable();

                $table->timestampsTz();

                $table->foreign(
                    'tenant_id'
                )
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
                    'evidence_file_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('files');

                $table->foreign([
                    'tenant_id',
                    'created_by_user_id',
                ])
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->foreign([
                    'tenant_id',
                    'verified_by_user_id',
                ])
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->foreign([
                    'tenant_id',
                    'rejected_by_user_id',
                ])
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->unique([
                    'id',
                    'tenant_id',
                ]);

                $table->unique([
                    'tenant_id',
                    'idempotency_key',
                ]);

                $table->unique([
                    'tenant_id',
                    'provider',
                    'provider_transaction_id',
                ]);

                $table->index([
                    'tenant_id',
                    'status',
                    'paid_at',
                ]);

                $table->index([
                    'tenant_id',
                    'customer_id',
                    'paid_at',
                ]);
            }
        );

        Schema::create(
            'payment_allocations',
            function (Blueprint $table): void {
                $table->ulid('id')
                    ->primary();

                $table->ulid(
                    'tenant_id'
                );

                $table->ulid(
                    'payment_id'
                );

                $table->ulid(
                    'invoice_id'
                );

                $table->decimal(
                    'allocated_amount',
                    18,
                    2
                );

                $table->timestampsTz();

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign([
                    'payment_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('payments');

                $table->foreign([
                    'invoice_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('invoices');

                $table->unique([
                    'tenant_id',
                    'payment_id',
                    'invoice_id',
                ]);

                $table->index([
                    'tenant_id',
                    'invoice_id',
                ]);
            }
        );

        Schema::create(
            'payment_reversals',
            function (Blueprint $table): void {
                $table->ulid('id')
                    ->primary();

                $table->ulid(
                    'tenant_id'
                );

                $table->ulid(
                    'payment_id'
                );

                $table->decimal(
                    'amount',
                    18,
                    2
                );

                $table->text(
                    'reason'
                );

                $table->ulid(
                    'reversed_by_user_id'
                );

                $table->timestampTz(
                    'reversed_at'
                );

                $table->timestampsTz();

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign([
                    'payment_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('payments');

                $table->foreign([
                    'tenant_id',
                    'reversed_by_user_id',
                ])
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->index([
                    'tenant_id',
                    'payment_id',
                    'reversed_at',
                ]);
            }
        );

        DB::statement(
            "ALTER TABLE payment_provider_accounts
             ADD CONSTRAINT payment_provider_environment_check
             CHECK (
                environment IN ('SANDBOX', 'PRODUCTION')
             )"
        );

        DB::statement(
            "ALTER TABLE payment_provider_accounts
             ADD CONSTRAINT payment_provider_connection_status_check
             CHECK (
                connection_status IN (
                    'DISCONNECTED',
                    'CONNECTED',
                    'DEGRADED',
                    'REAUTH_REQUIRED'
                )
             )"
        );

        DB::statement(
            "ALTER TABLE payments
             ADD CONSTRAINT payments_amount_positive_check
             CHECK (amount > 0)"
        );

        DB::statement(
            "ALTER TABLE payments
             ADD CONSTRAINT payments_method_check
             CHECK (
                method IN (
                    'BANK_TRANSFER',
                    'STATIC_QR',
                    'MIDTRANS'
                )
             )"
        );

        DB::statement(
            "ALTER TABLE payments
             ADD CONSTRAINT payments_status_check
             CHECK (
                status IN (
                    'PENDING',
                    'VERIFIED',
                    'REJECTED',
                    'REVERSED'
                )
             )"
        );

        DB::statement(
            "ALTER TABLE payment_allocations
             ADD CONSTRAINT payment_allocations_amount_positive_check
             CHECK (allocated_amount > 0)"
        );

        DB::statement(
            "ALTER TABLE payment_reversals
             ADD CONSTRAINT payment_reversals_amount_positive_check
             CHECK (amount > 0)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payment_reversals'
        );

        Schema::dropIfExists(
            'payment_allocations'
        );

        Schema::dropIfExists(
            'payments'
        );

        Schema::dropIfExists(
            'payment_provider_accounts'
        );

        Schema::dropIfExists(
            'tenant_payment_settings'
        );
    }
};
