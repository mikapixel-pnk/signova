<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'auth_otp_challenges',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->foreignUlid('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string(
                    'purpose',
                    32
                );

                $table->string(
                    'channel',
                    32
                );

                /*
                 * Never store OTP plaintext.
                 */
                $table->string(
                    'code_hash'
                );

                $table->unsignedSmallInteger(
                    'attempt_count'
                )->default(0);

                $table->unsignedSmallInteger(
                    'max_attempts'
                )->default(5);

                $table->timestampTz(
                    'expires_at'
                );

                $table->timestampTz(
                    'resend_available_at'
                );

                $table->timestampTz(
                    'consumed_at'
                )->nullable();

                $table->timestampTz(
                    'invalidated_at'
                )->nullable();

                /*
                 * Audit-ready metadata.
                 * Raw destination/IP/UA are not persisted.
                 */
                $table->string(
                    'delivery_target_hash',
                    64
                )->nullable();

                $table->string(
                    'requested_ip_hash',
                    64
                )->nullable();

                $table->string(
                    'user_agent_hash',
                    64
                )->nullable();

                $table->timestamps();

                $table->index([
                    'user_id',
                    'purpose',
                    'channel',
                    'created_at',
                ], 'auth_otp_user_purpose_channel_idx');

                $table->index([
                    'expires_at',
                    'consumed_at',
                    'invalidated_at',
                ], 'auth_otp_lifecycle_idx');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'auth_otp_challenges'
        );
    }
};
