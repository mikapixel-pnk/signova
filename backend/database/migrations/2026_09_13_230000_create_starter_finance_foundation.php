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
            'cash_accounts',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');

                $table->string(
                    'name',
                    190
                );

                $table->string(
                    'type',
                    16
                );

                $table->string(
                    'bank_name',
                    100
                )->nullable();

                $table->string(
                    'account_number',
                    100
                )->nullable();

                $table->string(
                    'account_name',
                    190
                )->nullable();

                $table->string(
                    'currency',
                    3
                )->default('IDR');

                $table->string(
                    'status',
                    16
                )->default('ACTIVE');

                $table->boolean(
                    'is_default'
                )->default(false);

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

                $table->foreign([
                    'tenant_id',
                    'created_by_user_id',
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

                $table->index([
                    'tenant_id',
                    'status',
                    'type',
                ]);
            }
        );

        Schema::create(
            'cash_transactions',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');

                $table->ulid(
                    'cash_account_id'
                );

                $table->string(
                    'direction',
                    8
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
                    'occurred_at'
                );

                $table->string(
                    'source_type',
                    32
                );

                $table->ulid(
                    'source_id'
                )->nullable();

                $table->string(
                    'reference',
                    190
                )->nullable();

                $table->text(
                    'description'
                )->nullable();

                $table->ulid(
                    'reversal_of_transaction_id'
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

                $table->foreign([
                    'cash_account_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('cash_accounts');

                $table->foreign([
                    'tenant_id',
                    'created_by_user_id',
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

                $table->foreign([
                    'reversal_of_transaction_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('cash_transactions');

                /*
                 * source_id NULL diperbolehkan untuk transaksi manual.
                 * Untuk source bisnis seperti PAYMENT/EXPENSE,
                 * unique ini menjadi idempotency guard.
                 */
                $table->unique(
                    [
                        'tenant_id',
                        'source_type',
                        'source_id',
                    ],
                    'cash_transactions_source_unique'
                );

                $table->index([
                    'tenant_id',
                    'cash_account_id',
                    'occurred_at',
                ]);

                $table->index([
                    'tenant_id',
                    'direction',
                    'occurred_at',
                ]);
            }
        );

        Schema::create(
            'expenses',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid('tenant_id');

                $table->ulid(
                    'cash_account_id'
                )->nullable();

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
                    'incurred_at'
                );

                /*
                 * Starter memakai kategori teks sederhana.
                 * Master category dapat dinormalisasi nanti
                 * jika Business/Pro memang membutuhkannya.
                 */
                $table->string(
                    'category',
                    100
                )->nullable();

                $table->text(
                    'description'
                );

                $table->string(
                    'status',
                    16
                )->default('DRAFT');

                $table->ulid(
                    'created_by_user_id'
                )->nullable();

                $table->ulid(
                    'posted_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'posted_at'
                )->nullable();

                $table->ulid(
                    'voided_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'voided_at'
                )->nullable();

                $table->text(
                    'void_reason'
                )->nullable();

                $table->timestampsTz();

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign([
                    'cash_account_id',
                    'tenant_id',
                ])
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('cash_accounts');

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
                    'posted_by_user_id',
                ])
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->foreign([
                    'tenant_id',
                    'voided_by_user_id',
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

                $table->index([
                    'tenant_id',
                    'status',
                    'incurred_at',
                ]);

                $table->index([
                    'tenant_id',
                    'cash_account_id',
                    'incurred_at',
                ]);
            }
        );

        DB::statement(
            "ALTER TABLE cash_accounts
             ADD CONSTRAINT cash_accounts_type_check
             CHECK (type IN ('CASH', 'BANK'))"
        );

        DB::statement(
            "ALTER TABLE cash_accounts
             ADD CONSTRAINT cash_accounts_status_check
             CHECK (status IN ('ACTIVE', 'INACTIVE'))"
        );

        DB::statement(
            "ALTER TABLE cash_accounts
             ADD CONSTRAINT cash_accounts_currency_check
             CHECK (currency = 'IDR')"
        );

        DB::statement(
            "CREATE UNIQUE INDEX
             cash_accounts_one_default_per_tenant_idx
             ON cash_accounts (tenant_id)
             WHERE is_default = TRUE"
        );

        DB::statement(
            "ALTER TABLE cash_transactions
             ADD CONSTRAINT cash_transactions_direction_check
             CHECK (direction IN ('IN', 'OUT'))"
        );

        DB::statement(
            "ALTER TABLE cash_transactions
             ADD CONSTRAINT cash_transactions_amount_positive_check
             CHECK (amount > 0)"
        );

        DB::statement(
            "ALTER TABLE cash_transactions
             ADD CONSTRAINT cash_transactions_currency_check
             CHECK (currency = 'IDR')"
        );

        DB::statement(
            "ALTER TABLE cash_transactions
             ADD CONSTRAINT cash_transactions_source_type_check
             CHECK (
                source_type IN (
                    'PAYMENT',
                    'PAYMENT_REVERSAL',
                    'MANUAL_INCOME',
                    'EXPENSE',
                    'EXPENSE_VOID'
                )
             )"
        );

        DB::statement(
            "ALTER TABLE cash_transactions
             ADD CONSTRAINT cash_transactions_no_self_reversal_check
             CHECK (
                reversal_of_transaction_id IS NULL
                OR reversal_of_transaction_id <> id
             )"
        );

        DB::statement(
            "ALTER TABLE expenses
             ADD CONSTRAINT expenses_amount_positive_check
             CHECK (amount > 0)"
        );

        DB::statement(
            "ALTER TABLE expenses
             ADD CONSTRAINT expenses_currency_check
             CHECK (currency = 'IDR')"
        );

        DB::statement(
            "ALTER TABLE expenses
             ADD CONSTRAINT expenses_status_check
             CHECK (
                status IN (
                    'DRAFT',
                    'POSTED',
                    'VOID'
                )
             )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'expenses'
        );

        Schema::dropIfExists(
            'cash_transactions'
        );

        Schema::dropIfExists(
            'cash_accounts'
        );
    }
};
