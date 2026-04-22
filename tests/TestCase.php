<?php

namespace Akhtar\LaravelDebugTracer\Tests;

use Akhtar\LaravelDebugTracer\DebugTracerServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [DebugTracerServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('debug-tracer.enabled', true);
        $app['config']->set('debug-tracer.route_middleware', []);
        $app['config']->set('debug-tracer.storage_path', __DIR__.'/../tmp/debug-traces');
        $app['config']->set('debug-tracer.attach_api_middleware', false);
    }
}
