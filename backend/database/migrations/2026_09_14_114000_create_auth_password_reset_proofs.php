<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'auth_password_reset_proofs',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->foreignUlid('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->foreignUlid(
                    'otp_challenge_id'
                )->constrained(
                    'auth_otp_challenges'
                )->cascadeOnDelete();

                $table->string(
                    'token_hash'
                );

                $table->timestampTz(
                    'expires_at'
                );

                $table->timestampTz(
                    'consumed_at'
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'user_id',
                        'expires_at',
                        'consumed_at',
                    ],
                    'password_reset_proof_lifecycle_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'auth_password_reset_proofs'
        );
    }
};
