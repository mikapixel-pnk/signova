<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $status = 'ok';

        $checks = [
            'application' => [
                'status' => 'ok',
            ],
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        foreach ($checks as $check) {
            if (($check['status'] ?? 'error') !== 'ok') {
                $status = 'degraded';
                break;
            }
        }

        return response()->json([
            'status' => $status,
            'service' => 'signova-api',
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $status === 'ok' ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $result = DB::selectOne(
                'select current_database() as database_name'
            );

            return [
                'status' => 'ok',
                'database' => $result->database_name ?? null,
            ];
        } catch (Throwable) {
            return [
                'status' => 'error',
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            $pong = Redis::connection()->ping();

            return [
                'status' => $pong ? 'ok' : 'error',
            ];
        } catch (Throwable) {
            return [
                'status' => 'error',
            ];
        }
    }
}
