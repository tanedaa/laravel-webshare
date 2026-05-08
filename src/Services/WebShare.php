<?php

namespace Tanedaa\LaravelWebShare\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tanedaa\LaravelWebShare\Exceptions\MissingApiKeyException;
use Tanedaa\LaravelWebShare\Exceptions\NoValidProxyException;
use Tanedaa\LaravelWebShare\Models\Proxy;
use UnexpectedValueException;

class WebShare
{
    public function updateProxyList(int $pageSize = 100): int
    {
        $proxies = $this->getProxyList($pageSize);
        $results = $proxies['results'] ?? [];

        DB::transaction(function () use ($results): void {
            foreach ($results as $proxy) {
                $normalized = $this->normalizeProxyPayload($proxy);

                Proxy::updateOrCreate(
                    ['proxy_id' => $normalized['proxy_id']],
                    $normalized
                );
            }
        });

        return count($results);
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
            $this->assertProxyListResponse($data);

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
            'proxy_id' => $proxy->proxy_id,
            'address' => $proxy->proxy_address . ':' . $proxy->port,
            'ip' => $proxy->proxy_address,
            'port' => $proxy->port,
            'is_valid' => $proxy->is_valid,
            'country_code' => $proxy->country_code,
            'city_name' => $proxy->city_name,
            'asn_name' => $proxy->asn_name,
            'asn_number' => $proxy->asn_number,
        ];
    }

    public function getRandomProxyCredentials(): array
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
        $host = $this->normalizeProxyHost((string) $proxy->proxy_address);
        $port = $this->normalizeProxyPort($proxy->port);

        return "http://$username:$password@$host:$port";
    }

    private function sendRequestToWebShare(string $path, array $payload = []): Response
    {
        $apiKey = trim((string) config('webshare.api_key', ''));

        if ($apiKey === '') {
            throw new MissingApiKeyException();
        }

        $baseUrl = $this->validatedBaseUrl();

        $request = Http::acceptJson()
            ->timeout($this->configInteger('timeout', 10, 1))
            ->connectTimeout($this->configInteger('connect_timeout', 5, 1))
            ->withToken($apiKey, 'Token');

        $retryTimes = $this->configInteger('retry_times', 2, 0);

        if ($retryTimes > 0) {
            $request->retry(
                $retryTimes,
                $this->configInteger('retry_sleep_milliseconds', 250, 0)
            );
        }

        return $request->get($baseUrl . ltrim($path, '/'), $payload);
    }

    private function assertProxyListResponse(mixed $data): void
    {
        if (! is_array($data) || ! array_key_exists('results', $data) || ! is_array($data['results'])) {
            throw new UnexpectedValueException('Invalid WebShare proxy list response.');
        }
    }

    private function normalizeProxyPayload(mixed $proxy): array
    {
        if (! is_array($proxy)) {
            throw new UnexpectedValueException('Invalid WebShare proxy record: expected an object.');
        }

        return [
            'proxy_id' => $this->requiredString($proxy, 'id'),
            'username' => $this->requiredString($proxy, 'username'),
            'password' => $this->requiredString($proxy, 'password', null),
            'proxy_address' => $this->normalizeProxyHost($this->requiredString($proxy, 'proxy_address')),
            'port' => $this->normalizeProxyPort($proxy['port'] ?? null),
            'is_valid' => $this->normalizeBoolean($proxy['valid'] ?? null),
            'country_code' => $this->optionalString($proxy, 'country_code'),
            'city_name' => $this->optionalString($proxy, 'city_name'),
            'asn_name' => $this->optionalString($proxy, 'asn_name'),
            'asn_number' => $this->optionalString($proxy, 'asn_number'),
        ];
    }

    private function requiredString(array $payload, string $key, ?int $maxLength = 255): string
    {
        if (! array_key_exists($key, $payload)) {
            throw new UnexpectedValueException("Invalid WebShare proxy record: missing {$key}.");
        }

        $value = trim((string) $payload[$key]);

        if ($value === '') {
            throw new UnexpectedValueException("Invalid WebShare proxy record: empty {$key}.");
        }

        if ($maxLength !== null && strlen($value) > $maxLength) {
            throw new UnexpectedValueException("Invalid WebShare proxy record: {$key} is too long.");
        }

        return $value;
    }

    private function optionalString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        $value = trim((string) $payload[$key]);

        if ($value === '') {
            return null;
        }

        if (strlen($value) > 255) {
            throw new UnexpectedValueException("Invalid WebShare proxy record: {$key} is too long.");
        }

        return $value;
    }

    private function normalizeProxyHost(string $host): string
    {
        $host = trim($host);

        if (
            $host === ''
            || str_contains($host, '/')
            || str_contains($host, '@')
            || str_contains($host, ':')
        ) {
            throw new UnexpectedValueException('Invalid WebShare proxy host.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        if (preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/i', $host) === 1) {
            return $host;
        }

        throw new UnexpectedValueException('Invalid WebShare proxy host.');
    }

    private function normalizeProxyPort(mixed $port): int
    {
        $port = filter_var($port, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 65535,
            ],
        ]);

        if ($port === false) {
            throw new UnexpectedValueException('Invalid WebShare proxy port.');
        }

        return $port;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolean === null) {
            throw new UnexpectedValueException('Invalid WebShare proxy validity flag.');
        }

        return $boolean;
    }

    private function validatedBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('webshare.base_url', 'https://proxy.webshare.io/api/v2/'), '/') . '/';
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : $host;

        if ($scheme !== 'https' || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException('WebShare API URL must be a valid HTTPS URL.');
        }

        if ($host !== 'proxy.webshare.io' && ! (bool) config('webshare.allow_custom_base_url', false)) {
            throw new InvalidArgumentException('Custom WebShare API URLs require webshare.allow_custom_base_url to be enabled.');
        }

        return $baseUrl;
    }

    private function configInteger(string $key, int $default, int $minimum): int
    {
        $value = filter_var(config("webshare.{$key}", $default), FILTER_VALIDATE_INT);

        if ($value === false || $value < $minimum) {
            return $default;
        }

        return $value;
    }
}
