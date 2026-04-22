<?php

use Akhtar\LaravelDebugTracer\Http\Controllers\DashboardController;
use Akhtar\LaravelDebugTracer\Http\Controllers\DebugSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('debug-tracer.route_middleware', ['api']))
    ->prefix('debug')
    ->group(function (): void {
        Route::post('/start', [DebugSessionController::class, 'start']);
        Route::post('/stop', [DebugSessionController::class, 'stop']);
        Route::get('/export/{sessionId}', [DebugSessionController::class, 'export']);
    });

Route::middleware(['web'])->group(function () {
    Route::get('/debug-dashboard', [DashboardController::class, 'index']);
    Route::get('/debug-dashboard/logs/{sessionId}', [DashboardController::class, 'logs']);
});
