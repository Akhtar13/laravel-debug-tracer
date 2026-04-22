<?php

namespace Akhtar\LaravelDebugTracer;

use Akhtar\LaravelDebugTracer\Console\Commands\CleanupDebugTracesCommand;
use Akhtar\LaravelDebugTracer\Http\Middleware\BindDebugSession;
use Akhtar\LaravelDebugTracer\Http\Middleware\TraceHttpLifecycle;
use Akhtar\LaravelDebugTracer\Services\DebugTracer;
use Akhtar\LaravelDebugTracer\Services\TraceStorage;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
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
            $router = $this->app['router'];
            $router->pushMiddlewareToGroup('api', BindDebugSession::class);
            $router->pushMiddlewareToGroup('api', TraceHttpLifecycle::class);
        }

        DB::listen(function ($query): void {
            if (! app()->bound('debug.session_id')) {
                return;
            }

            app(DebugTracer::class)->capture([
                'type' => 'db',
                'query' => $query->sql,
                'duration_ms' => $query->time,
            ]);
        });

        Event::listen(RequestSending::class, function (RequestSending $event): void {
            if (! app()->bound('debug.session_id')) {
                return;
            }

            app(DebugTracer::class)->capture([
                'type' => 'http_request',
                'url' => (string) $event->request->url(),
                'method' => $event->request->method(),
            ]);
        });

        Event::listen(ResponseReceived::class, function (ResponseReceived $event): void {
            if (! app()->bound('debug.session_id')) {
                return;
            }

            app(DebugTracer::class)->capture([
                'type' => 'http_response',
                'url' => (string) $event->request->url(),
                'status' => $event->response->status(),
            ]);
        });

        Event::listen(ConnectionFailed::class, function (ConnectionFailed $event): void {
            if (! app()->bound('debug.session_id')) {
                return;
            }

            app(DebugTracer::class)->capture([
                'type' => 'http_error',
                'url' => (string) $event->request->url(),
            ]);
        });

        Queue::createPayloadUsing(function () {
            if (! app()->bound('debug.session_id')) {
                return [];
            }

            return ['debug_session_id' => app('debug.session_id')];
        });

        if ($this->app->runningInConsole()) {
            $this->commands([CleanupDebugTracesCommand::class]);

            $this->app->booted(function (): void {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
                $schedule->command('debug-tracer:cleanup')->everyFiveMinutes();
            });
        }
    }
}
