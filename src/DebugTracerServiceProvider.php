<?php

namespace Akhtar\LaravelDebugTracer;

use Akhtar\LaravelDebugTracer\Console\Commands\CleanupDebugTracesCommand;
use Akhtar\LaravelDebugTracer\Services\DebugTracer;
use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Illuminate\Support\ServiceProvider;

class DebugTracerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/debug-tracer.php', 'debug-tracer');

        $this->app->singleton(TraceStorage::class, function () {
            return new TraceStorage(config('debug-tracer.storage_path'));
        });

        $this->app->singleton(DebugTracer::class, fn () => new DebugTracer($this->app->make(TraceStorage::class)));
        $this->app->singleton('tracer', fn () => $this->app->make(DebugTracer::class));
        $this->loadViewsFrom(__DIR__.'/resources/views', 'debug-tracer');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/debug-tracer.php' => config_path('debug-tracer.php'),
        ], 'debug-tracer-config');

        if (config('debug-tracer.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/debug-tracer.php');
        }

        if (config('debug-tracer.attach_api_middleware', true) && $this->app->bound('router')) {
            $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
                ->pushMiddleware(\Akhtar\LaravelDebugTracer\Http\Middleware\BindDebugSession::class);
            $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
                ->pushMiddleware(\Akhtar\LaravelDebugTracer\Http\Middleware\TraceHttpLifecycle::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CleanupDebugTracesCommand::class]);

            $this->app->booted(function (): void {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
                $schedule->command('debug-tracer:cleanup')->everyFiveMinutes();
            });
        }
    }
}
