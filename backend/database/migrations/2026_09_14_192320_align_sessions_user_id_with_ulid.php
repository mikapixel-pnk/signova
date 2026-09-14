<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * SIGNOVA users use ULID CHAR(26).
         * Laravel's default session migration creates BIGINT user_id,
         * which cannot store SIGNOVA user identifiers.
         */
        DB::statement(
            'ALTER TABLE sessions
             ALTER COLUMN user_id
             TYPE CHAR(26)
             USING user_id::text'
        );
    }

    public function down(): void
    {
        /*
         * Session rows are ephemeral.
         * Clear them before restoring Laravel's default BIGINT type.
         */
        DB::table('sessions')->delete();

        DB::statement(
            'ALTER TABLE sessions
             ALTER COLUMN user_id
             TYPE BIGINT
             USING NULL'
        );
    }
};
