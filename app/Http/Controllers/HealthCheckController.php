<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    public function app(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function db(): JsonResponse
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $start) * 1000, 2);

            return response()->json([
                'status' => 'ok',
                'connection' => config('database.default'),
                'response_time_ms' => $ms,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database unreachable',
            ], 503);
        }
    }

    public function redis(): JsonResponse
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $ms = round((microtime(true) - $start) * 1000, 2);

            return response()->json([
                'status' => 'ok',
                'response_time_ms' => $ms,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Redis unreachable',
            ], 503);
        }
    }

    public function queue(): JsonResponse
    {
        try {
            $failedCount = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();

            $pendingCount = 0;
            try {
                $pendingCount = (int) Redis::llen('queues:critical')
                              + (int) Redis::llen('queues:default')
                              + (int) Redis::llen('queues:low');
            } catch (\Throwable) {
                // Redis may not be available; still report failed_jobs
            }

            return response()->json([
                'status' => $failedCount > 20 ? 'degraded' : 'ok',
                'pending_jobs' => $pendingCount,
                'failed_last_hour' => $failedCount,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Queue check failed',
            ], 503);
        }
    }
}
