<?php

namespace Tanedaa\LaravelWebShare\Providers;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Tanedaa\LaravelWebShare\Console\Commands\UpdateWebShareProxiesCommand;
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
        $applyWebShareProxy = function (PendingRequest $request): PendingRequest {
            $proxyUrl = app(WebShare::class)->getRandomProxyUrl();

            return $request->withOptions([
                'proxy' => $proxyUrl,
            ]);
        };

        Http::macro('webshare', function () use ($applyWebShareProxy) {
            return $applyWebShareProxy(Http::withOptions([]));
        });

        HttpFactory::macro('webshare', function () use ($applyWebShareProxy) {
            return $applyWebShareProxy($this->withOptions([]));
        });

        PendingRequest::macro('webshare', function () use ($applyWebShareProxy) {
            return $applyWebShareProxy($this);
        });

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                UpdateWebShareProxiesCommand::class,
            ]);

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
