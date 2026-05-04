<?php

use Illuminate\Support\Facades\Route;
use Rakib\ServerLens\Http\Controllers\ApiController;
use Rakib\ServerLens\Http\Controllers\DashboardController;

$prefix     = config('server-lens.route_prefix', 'ops');
$middleware = config('server-lens.middleware', ['web', 'auth']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('server-lens.')
    ->group(function () {
        Route::get('/',     [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/poll', [DashboardController::class, 'poll'])->name('poll');
    });

if (config('server-lens.api.enabled', false)) {
    Route::prefix($prefix . '/api')
        ->name('server-lens.api.')
        ->group(function () {
            Route::get('/snapshot', [ApiController::class, 'snapshot'])->name('snapshot');
            Route::get('/health',   [ApiController::class, 'health'])->name('health');
            Route::get('/metrics',  [ApiController::class, 'metrics'])->name('metrics');
        });
}
