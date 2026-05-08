# Laravel WebShare

Laravel package for integrating [webshare.io](https://www.webshare.io/) proxies into your Laravel application.

It supports:
- Syncing purchased proxies from WebShare into your database
- Using a random valid proxy in outgoing Laravel HTTP requests via `Http::webshare()`
- Accessing random proxy data/URL for other clients

## Requirements

- PHP 8.1+
- Laravel 10+

## Installation

```bash
composer require tanedaa/laravel-webshare
```

## Publish Assets

Publish both config and migrations:

```bash
php artisan vendor:publish --tag=webshare
```

Or publish separately:

```bash
php artisan vendor:publish --tag=webshare-config
php artisan vendor:publish --tag=webshare-migrations
```

Run migrations:

```bash
php artisan migrate
```

## Configuration

Set your WebShare API key in `.env`:

```env
WEBSHARE_API_KEY=your_webshare_api_key
WEBSHARE_API_URL=https://proxy.webshare.io/api/v2/
WEBSHARE_PROXY_TABLE=webshare_proxies
WEBSHARE_TIMEOUT=10
WEBSHARE_CONNECT_TIMEOUT=5
WEBSHARE_RETRY_TIMES=2
WEBSHARE_RETRY_SLEEP_MILLISECONDS=250
```

Config file: `config/webshare.php`

Custom API hosts are rejected by default. If you intentionally point the package at a non-WebShare host, set `WEBSHARE_ALLOW_CUSTOM_API_URL=true`.

## Sync Proxies

Use the built-in command:

```bash
php artisan webshare:update-proxies
```

Optional page size:

```bash
php artisan webshare:update-proxies --page-size=200
```

The command paginates through WebShare API results until all purchased proxies are fetched, then upserts them into the configured proxy table. By default, that table is `webshare_proxies`.

## Scheduling

Laravel 11+ (`routes/console.php`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('webshare:update-proxies --page-size=100')->hourly();
```

Laravel 10 (`app/Console/Kernel.php` inside `schedule()`):

```php
$schedule->command('webshare:update-proxies --page-size=100')->hourly();
```

## Usage

### 1. Laravel HTTP Client Macro

Use a random valid proxy automatically:

```php
use Illuminate\Support\Facades\Http;

$response = Http::webshare()->get('https://api.ipify.org?format=json');
```

You can still chain regular HTTP options:

```php
$response = Http::timeout(15)->webshare()->get('https://httpbin.org/ip');
```

### 2. Facade

```php
use Tanedaa\LaravelWebShare\Facades\WebShare;

$proxyUrl = WebShare::getRandomProxyUrl();
$proxyData = WebShare::getRandomProxyData();
$proxyCredentials = WebShare::getRandomProxyCredentials();
```

`getRandomProxyData()` returns safe proxy metadata without credentials:

```php
[
    'proxy_id' => '...',
    'address' => 'host:port',
    'ip' => 'host',
    'port' => 1234,
    'is_valid' => true,
    'country_code' => '...',
    'city_name' => '...',
    'asn_name' => '...',
    'asn_number' => '...',
]
```

`getRandomProxyCredentials()` is available when a non-Laravel HTTP client needs explicit credentials:

```php
[
    'address' => 'host:port',
    'ip' => 'host',
    'port' => 1234,
    'username' => '...',
    'password' => '...',
    'proxy_url' => 'http://user:pass@host:port',
]
```

Proxy passwords are stored using Laravel's encrypted Eloquent cast and are hidden when the proxy model is serialized.

### 3. Dependency Injection

```php
use Tanedaa\LaravelWebShare\Services\WebShare;

public function __invoke(WebShare $webShare)
{
    return $webShare->getRandomProxyData();
}
```
