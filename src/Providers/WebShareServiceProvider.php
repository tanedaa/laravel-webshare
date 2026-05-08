<?php

namespace Tanedaa\LaravelWebShare\Providers;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Tanedaa\LaravelWebShare\Services\WebShare;

class WebShareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/webshare.php', 'webshare');

        $this->app->singleton('webshare', function () {
            return new WebShare();
        });

        $this->app->singleton(WebShare::class, function () {
            return $this->app->make('webshare');
        });
    }

    public function boot(): void
    {
        Http::macro('webshare', function () {
            $proxyUrl = app(WebShare::class)->getRandomProxyUrl();

            return Http::withOptions([
                'proxy' => $proxyUrl,
            ]);
        });

        HttpFactory::macro('webshare', function () {
            $proxyUrl = app(WebShare::class)->getRandomProxyUrl();

            return $this->withOptions([
                'proxy' => $proxyUrl,
            ]);
        });

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $publishables = [
                __DIR__ . '/../../config/webshare.php' => config_path('webshare.php'),
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ];

            $this->publishes($publishables, 'webshare');

            $this->publishes([
                __DIR__ . '/../../config/webshare.php' => config_path('webshare.php'),
            ], 'webshare-config');

            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'webshare-migrations');
        }
    }
}
