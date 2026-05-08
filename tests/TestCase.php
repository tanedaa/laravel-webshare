<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase as Orchestra;
use Tanedaa\LaravelWebShare\Models\Proxy;
use Tanedaa\LaravelWebShare\Providers\WebShareServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            WebShareServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=');
        $app['config']->set('webshare.api_key', 'test-api-key');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--database' => 'testing']);
    }

    protected function proxyTable(): string
    {
        return (new Proxy())->getTable();
    }
}
