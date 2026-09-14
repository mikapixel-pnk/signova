<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantPlatformSuperAdmin extends Command
{
    protected $signature =
        'platform:grant-super-admin {email}';

    protected $description =
        'Grant SUPER_ADMIN platform role to an existing SIGNOVA user.';

    public function handle(): int
    {
        $email = strtolower(
            trim(
                (string) $this->argument('email')
            )
        );

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            $this->error(
                'User SIGNOVA tidak ditemukan.'
            );

            return self::FAILURE;
        }

        $role = DB::table(
            'platform_roles'
        )
            ->where(
                'code',
                'SUPER_ADMIN'
            )
            ->where(
                'status',
                'ACTIVE'
            )
            ->first();

        if (! $role) {
            $this->error(
                'Role SUPER_ADMIN belum tersedia. '
                .'Jalankan PlatformAccessControlSeeder.'
            );

            return self::FAILURE;
        }

        $exists = DB::table(
            'platform_user_roles'
        )
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                'platform_role_id',
                $role->id
            )
            ->exists();

        if ($exists) {
            $this->info(
                'User sudah memiliki role SUPER_ADMIN.'
            );

            return self::SUCCESS;
        }

        DB::table(
            'platform_user_roles'
        )->insert([
            'user_id' =>
                $user->id,

            'platform_role_id' =>
                $role->id,

            /*
             * Bootstrap awal bersifat explicit CLI.
             * Actor assignment terpisah akan
             * dilengkapi saat platform admin UI
             * dan audit management tersedia.
             */
            'assigned_by_user_id' =>
                null,

            'assigned_at' =>
                now(),

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->info(
            'SUPER_ADMIN berhasil diberikan.'
        );

        return self::SUCCESS;
    }
}
