<?php

use Akhtar\LaravelDebugTracer\Http\Controllers\DebugSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('debug-tracer.route_middleware', ['api', 'auth']))
    ->prefix('debug')
    ->group(function (): void {
        Route::post('/start', [DebugSessionController::class, 'start']);
        Route::post('/stop', [DebugSessionController::class, 'stop']);
        Route::get('/export/{sessionId}', [DebugSessionController::class, 'export']);
    });
