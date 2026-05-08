<?php

namespace Tanedaa\LaravelWebShare\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tanedaa\LaravelWebShare\Models\Proxy;

class WebShare
{
    private string $url = 'https://proxy.webshare.io/api/v2/';

    public function updateProxyList(): void
    {
        $proxies = $this->getProxyList();

        foreach ($proxies['results'] as $proxy)
        {
            Proxy::updateOrCreate(
                [
                    'proxy_id' => $proxy['id'],
                ],
                [
                    'username' => $proxy['username'],
                    'password' => $proxy['password'],
                    'proxy_address' => $proxy['proxy_address'],
                    'port' => $proxy['port'],
                    'is_valid' => $proxy['valid'],
                    'country_code' => $proxy['country_code'],
                    'city_name' => $proxy['city_name'],
                    'asn_name' => $proxy['asn_name'],
                    'asn_number' => $proxy['asn_number'],
                ]
            );
        }
    }

    public function getProxyList(): array
    {
        $response = $this->sendRequestToWebShare('proxy/list', [
            'mode' => 'direct',
            'page_size' => 100,
        ]);

        return $response->throw()->json();
    }

    public function getRandomProxy(): Proxy
    {
        $proxy = Proxy::query()
            ->where('is_valid', true)
            ->inRandomOrder()
            ->first();

        if (! $proxy) {
            throw new RuntimeException('No valid proxies found in the proxies table.');
        }

        return $proxy;
    }

    public function getRandomProxyData(): array
    {
        $proxy = $this->getRandomProxy();

        return [
            'address' => $proxy->proxy_address . ':' . $proxy->port,
            'ip' => $proxy->proxy_address,
            'port' => $proxy->port,
            'username' => $proxy->username,
            'password' => $proxy->password,
            'proxy_url' => $this->buildProxyUrl($proxy),
        ];
    }

    public function getRandomProxyUrl(): string
    {
        return $this->buildProxyUrl($this->getRandomProxy());
    }

    private function buildProxyUrl(Proxy $proxy): string
    {
        $username = rawurlencode((string) $proxy->username);
        $password = rawurlencode((string) $proxy->password);
        $host = $proxy->proxy_address;
        $port = $proxy->port;

        return "http://{$username}:{$password}@{$host}:{$port}";
    }

    private function sendRequestToWebShare(string $path, array $payload = []): Response
    {
        return Http::withHeaders([
            'Authorization' => 'Token ' . config('webshare.api_key'),
        ])->get($this->url . $path, $payload);
    }
}
