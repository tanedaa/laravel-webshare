<?php

namespace Tanedaa\LaravelWebShare\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tanedaa\LaravelWebShare\Exceptions\MissingApiKeyException;
use Tanedaa\LaravelWebShare\Exceptions\NoValidProxyException;
use Tanedaa\LaravelWebShare\Models\Proxy;

class WebShare
{
    private string $url = 'https://proxy.webshare.io/api/v2/';

    public function updateProxyList(int $pageSize = 100): int
    {
        $proxies = $this->getProxyList($pageSize);

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

        return count($proxies['results']);
    }

    public function getProxyList(int $pageSize = 100): array
    {
        $allResults = [];
        $page = 1;
        $totalCount = null;

        while (true) {
            $response = $this->sendRequestToWebShare('proxy/list', [
                'mode' => 'direct',
                'page_size' => $pageSize,
                'page' => $page,
            ]);

            $data = $response->throw()->json();

            $totalCount ??= $data['count'] ?? 0;
            $results = $data['results'] ?? [];
            $allResults = array_merge($allResults, $results);

            if (($data['next'] ?? null) === null) {
                break;
            }

            if (count($allResults) >= $totalCount) {
                break;
            }

            if (empty($results)) {
                break;
            }

            $page++;
        }

        return [
            'count' => $totalCount ?? count($allResults),
            'next' => null,
            'previous' => null,
            'results' => $allResults,
        ];
    }

    public function getRandomProxy(): Proxy
    {
        $proxy = Proxy::query()
            ->where('is_valid', true)
            ->inRandomOrder()
            ->first();

        if (! $proxy) {
            throw new NoValidProxyException();
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

        return "http://$username:$password@$host:$port";
    }

    private function sendRequestToWebShare(string $path, array $payload = []): Response
    {
        $apiKey = (string) config('webshare.api_key', '');

        if ($apiKey === '') {
            throw new MissingApiKeyException();
        }

        return Http::withHeaders([
            'Authorization' => 'Token ' . $apiKey,
        ])->get($this->url . $path, $payload);
    }
}
