<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');

            $table->string('name', 190);
            $table->string('legal_name', 190)->nullable();

            $table->text('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('postal_code', 32)->nullable();

            $table->string('phone', 64)->nullable();
            $table->string('whatsapp', 64)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('tax_id', 100)->nullable();

            $table->boolean('is_default')->default(false);
            $table->string('status', 32)->default('ACTIVE');

            $table->timestampsTz();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(
                ['id', 'tenant_id'],
                'business_profiles_id_tenant_unique'
            );

            $table->index(
                ['tenant_id', 'status', 'name'],
                'business_profiles_tenant_status_name_index'
            );
        });

        DB::statement(
            "ALTER TABLE business_profiles
             ADD CONSTRAINT business_profiles_status_check
             CHECK (status IN ('ACTIVE', 'INACTIVE'))"
        );

        DB::statement(
            "CREATE UNIQUE INDEX
             business_profiles_one_default_per_tenant_unique
             ON business_profiles (tenant_id)
             WHERE is_default = TRUE"
        );

        /*
         * Progressive multi-business migration:
         * every existing tenant receives exactly one default business.
         *
         * Existing tenant identity remains untouched. Business-specific
         * domains will move to tenant_id + business_id in later checkpoints.
         */
        DB::table('tenants')
            ->select([
                'id',
                'name',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id')
            ->get()
            ->each(function ($tenant): void {
                DB::table('business_profiles')->insert([
                    'id' => $tenant->id,
                    'tenant_id' => $tenant->id,
                    'name' => $tenant->name,
                    'legal_name' => null,
                    'address' => null,
                    'city' => null,
                    'province' => null,
                    'postal_code' => null,
                    'phone' => null,
                    'whatsapp' => null,
                    'email' => null,
                    'website' => null,
                    'tax_id' => null,
                    'is_default' => true,
                    'status' => 'ACTIVE',
                    'created_at' => $tenant->created_at,
                    'updated_at' => $tenant->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
