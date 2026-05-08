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
```

Config file: `config/webshare.php`

## Sync Proxies

Use the built-in command:

```bash
php artisan webshare:update-proxies
```

Optional page size:

```bash
php artisan webshare:update-proxies --page-size=200
```

The command paginates through WebShare API results until all purchased proxies are fetched, then upserts them into the `proxies` table.

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
```

`getRandomProxyData()` returns:

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

### 3. Dependency Injection

```php
use Tanedaa\LaravelWebShare\Services\WebShare;

public function __invoke(WebShare $webShare)
{
    return $webShare->getRandomProxyData();
}
```
