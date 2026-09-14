<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE sessions
             ALTER COLUMN user_id
             TYPE CHAR(26)
             USING user_id::text'
        );
    }

    public function down(): void
    {
        DB::table('sessions')->delete();

        DB::statement(
            'ALTER TABLE sessions
             ALTER COLUMN user_id
             TYPE BIGINT
             USING NULL'
        );
    }
};
