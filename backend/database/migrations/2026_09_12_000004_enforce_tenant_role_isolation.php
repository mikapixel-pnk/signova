<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(
                ['id', 'tenant_id'],
                'roles_id_tenant_unique'
            );
        });

        Schema::table('tenant_user_roles', function (Blueprint $table) {
            $table->dropForeign(['role_id']);

            $table->foreign(
                ['role_id', 'tenant_id'],
                'tenant_user_roles_role_tenant_foreign'
            )
                ->references(['id', 'tenant_id'])
                ->on('roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_user_roles', function (Blueprint $table) {
            $table->dropForeign(
                'tenant_user_roles_role_tenant_foreign'
            );

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_id_tenant_unique');
        });
    }
};
