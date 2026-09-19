<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'expenses',
            function (Blueprint $table): void {
                $table->ulid(
                    'submitted_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'submitted_at'
                )->nullable();

                $table->ulid(
                    'approved_by_user_id'
                )->nullable();

                $table->timestampTz(
                    'approved_at'
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

                $table->foreign(
                    [
                        'tenant_id',
                        'submitted_by_user_id',
                    ],
                    'expenses_tenant_submitted_by_user_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->foreign(
                    [
                        'tenant_id',
                        'approved_by_user_id',
                    ],
                    'expenses_tenant_approved_by_user_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->foreign(
                    [
                        'tenant_id',
                        'rejected_by_user_id',
                    ],
                    'expenses_tenant_rejected_by_user_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->unique(
                    [
                        'id',
                        'business_id',
                        'tenant_id',
                    ],
                    'expenses_id_business_tenant_unique'
                );
            }
        );

        DB::statement(
            'ALTER TABLE expenses '
            . 'DROP CONSTRAINT expenses_status_check'
        );

        DB::statement(
            "ALTER TABLE expenses
             ADD CONSTRAINT expenses_status_check
             CHECK (
                 status IN (
                     'DRAFT',
                     'PENDING_APPROVAL',
                     'REJECTED',
                     'POSTED',
                     'VOID'
                 )
             )"
        );

        Schema::create(
            'expense_status_history',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->ulid(
                    'tenant_id'
                );

                $table->ulid(
                    'business_id'
                );

                $table->ulid(
                    'expense_id'
                );

                $table->string(
                    'from_status',
                    16
                );

                $table->string(
                    'to_status',
                    16
                );

                $table->string(
                    'action',
                    32
                );

                $table->ulid(
                    'actor_user_id'
                )->nullable();

                $table->text(
                    'reason'
                )->nullable();

                $table->timestampTz(
                    'created_at'
                )->useCurrent();

                $table->foreign(
                    'tenant_id'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    [
                        'business_id',
                        'tenant_id',
                    ],
                    'expense_history_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'tenant_id',
                    ])
                    ->on('business_profiles');

                $table->foreign(
                    [
                        'expense_id',
                        'business_id',
                        'tenant_id',
                    ],
                    'expense_history_expense_business_tenant_foreign'
                )
                    ->references([
                        'id',
                        'business_id',
                        'tenant_id',
                    ])
                    ->on('expenses');

                $table->foreign(
                    [
                        'tenant_id',
                        'actor_user_id',
                    ],
                    'expense_history_tenant_actor_foreign'
                )
                    ->references([
                        'tenant_id',
                        'user_id',
                    ])
                    ->on('tenant_users');

                $table->index(
                    [
                        'tenant_id',
                        'business_id',
                        'expense_id',
                        'created_at',
                    ],
                    'expense_history_context_date_idx'
                );
            }
        );

        DB::statement(
            "ALTER TABLE expense_status_history
             ADD CONSTRAINT expense_history_from_status_check
             CHECK (
                 from_status IN (
                     'DRAFT',
                     'PENDING_APPROVAL',
                     'REJECTED',
                     'POSTED',
                     'VOID'
                 )
             )"
        );

        DB::statement(
            "ALTER TABLE expense_status_history
             ADD CONSTRAINT expense_history_to_status_check
             CHECK (
                 to_status IN (
                     'DRAFT',
                     'PENDING_APPROVAL',
                     'REJECTED',
                     'POSTED',
                     'VOID'
                 )
             )"
        );

        DB::statement(
            "ALTER TABLE expense_status_history
             ADD CONSTRAINT expense_history_action_check
             CHECK (
                 action IN (
                     'SUBMITTED',
                     'APPROVED',
                     'REJECTED',
                     'REVISED',
                     'DIRECT_POST',
                     'VOIDED'
                 )
             )"
        );
    }

    public function down(): void
    {
        DB::table('expenses')
            ->whereIn(
                'status',
                [
                    'PENDING_APPROVAL',
                    'REJECTED',
                ]
            )
            ->update([
                'status' => 'DRAFT',
            ]);

        Schema::dropIfExists(
            'expense_status_history'
        );

        DB::statement(
            'ALTER TABLE expenses '
            . 'DROP CONSTRAINT expenses_status_check'
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

        Schema::table(
            'expenses',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'expenses_tenant_submitted_by_user_foreign'
                );

                $table->dropForeign(
                    'expenses_tenant_approved_by_user_foreign'
                );

                $table->dropForeign(
                    'expenses_tenant_rejected_by_user_foreign'
                );

                $table->dropUnique(
                    'expenses_id_business_tenant_unique'
                );

                $table->dropColumn([
                    'submitted_by_user_id',
                    'submitted_at',
                    'approved_by_user_id',
                    'approved_at',
                    'rejected_by_user_id',
                    'rejected_at',
                    'rejection_reason',
                ]);
            }
        );
    }
};
