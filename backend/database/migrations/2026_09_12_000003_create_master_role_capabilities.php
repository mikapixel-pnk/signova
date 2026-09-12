<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_role_capabilities', function (Blueprint $table) {
            $table->ulid('master_role_id');
            $table->ulid('capability_id');

            $table->string('effect', 16)->default('ALLOW');

            $table->timestampsTz();

            $table->primary([
                'master_role_id',
                'capability_id',
            ]);

            $table->foreign('master_role_id')
                ->references('id')
                ->on('master_roles')
                ->cascadeOnDelete();

            $table->foreign('capability_id')
                ->references('id')
                ->on('capabilities')
                ->cascadeOnDelete();

            $table->index([
                'capability_id',
                'effect',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_role_capabilities');
    }
};
