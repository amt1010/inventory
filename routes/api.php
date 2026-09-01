<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
|
| Used by Railway's health check mechanism to verify the service is truly
| ready before routing traffic to it. Checks both the database (MySQL)
| and Redis (used by the queue worker) so a deploy is only marked healthy
| once every dependency the app relies on is actually reachable.
|
*/
Route::get('/health', function () {
    $checks = [
        'database' => false,
        'redis' => false,
    ];

    try {
        DB::connection()->getPdo();
        DB::connection()->select('select 1');
        $checks['database'] = true;
    } catch (\Throwable $e) {
        $checks['database'] = false;
    }

    try {
        Redis::connection()->ping();
        $checks['redis'] = true;
    } catch (\Throwable $e) {
        $checks['redis'] = false;
    }

    $healthy = ! in_array(false, $checks, true);

    return response()->json([
        'status' => $healthy ? 'ok' : 'unhealthy',
        'checks' => $checks,
    ], $healthy ? 200 : 503);
})->name('api.health');
