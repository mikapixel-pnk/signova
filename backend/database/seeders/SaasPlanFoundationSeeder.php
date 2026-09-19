<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaasPlanFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'STARTER',
                'name' => 'Starter',
            ],
            [
                'code' => 'BUSINESS',
                'name' => 'Bisnis',
            ],
            [
                'code' => 'PRO',
                'name' => 'Pro',
            ],
        ];

        $now = now();

        foreach ($plans as $plan) {
            $existing = DB::table('plans')
                ->where('code', $plan['code'])
                ->first();

            if ($existing !== null) {
                DB::table('plans')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $plan['name'],
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('plans')->insert([
                'id' => (string) Str::ulid(),
                'code' => $plan['code'],
                'name' => $plan['name'],
                'description' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
