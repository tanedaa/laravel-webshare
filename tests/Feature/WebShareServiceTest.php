<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tanedaa\LaravelWebShare\Exceptions\MissingApiKeyException;
use Tanedaa\LaravelWebShare\Exceptions\NoValidProxyException;
use Tanedaa\LaravelWebShare\Models\Proxy;
use Tanedaa\LaravelWebShare\Services\WebShare;
use Tests\TestCase;

class WebShareServiceTest extends TestCase
{
    public function test_it_updates_proxy_list_from_webshare_api(): void
    {
        Http::fake([
            'https://proxy.webshare.io/api/v2/proxy/list*' => Http::response([
                'results' => [
                    [
                        'id' => 'proxy-1',
                        'username' => 'user1',
                        'password' => 'pass1',
                        'proxy_address' => '192.168.1.10',
                        'port' => 8080,
                        'valid' => true,
                        'country_code' => 'US',
                        'city_name' => 'New York',
                        'asn_name' => 'Example ASN',
                        'asn_number' => '12345',
                    ],
                ],
            ], 200),
        ]);

        app(WebShare::class)->updateProxyList();

        $this->assertDatabaseHas($this->proxyTable(), [
            'proxy_id' => 'proxy-1',
            'proxy_address' => '192.168.1.10',
            'port' => 8080,
            'is_valid' => 1,
        ]);

        $storedPassword = DB::table($this->proxyTable())
            ->where('proxy_id', 'proxy-1')
            ->value('password');

        $this->assertNotSame('pass1', $storedPassword);
        $this->assertSame('pass1', Proxy::query()->firstWhere('proxy_id', 'proxy-1')->password);
        $this->assertArrayNotHasKey('password', Proxy::query()->firstWhere('proxy_id', 'proxy-1')->toArray());
    }

    public function test_it_returns_safe_random_proxy_data(): void
    {
        Proxy::query()->create([
            'proxy_id' => 'proxy-rand',
            'username' => 'my-user',
            'password' => 'my-pass',
            'proxy_address' => '10.0.0.2',
            'port' => 9000,
            'is_valid' => true,
            'country_code' => 'GR',
            'city_name' => 'Athens',
            'asn_name' => 'Test ASN',
            'asn_number' => '9999',
        ]);

        $data = app(WebShare::class)->getRandomProxyData();

        $this->assertSame('proxy-rand', $data['proxy_id']);
        $this->assertSame('10.0.0.2:9000', $data['address']);
        $this->assertSame('10.0.0.2', $data['ip']);
        $this->assertSame(9000, $data['port']);
        $this->assertSame(true, $data['is_valid']);
        $this->assertSame('GR', $data['country_code']);
        $this->assertSame('Athens', $data['city_name']);
        $this->assertSame('Test ASN', $data['asn_name']);
        $this->assertSame('9999', $data['asn_number']);
        $this->assertArrayNotHasKey('username', $data);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('proxy_url', $data);
    }

    public function test_it_returns_random_proxy_credentials_when_explicitly_requested(): void
    {
        Proxy::query()->create([
            'proxy_id' => 'proxy-rand-credentials',
            'username' => 'my-user',
            'password' => 'my-pass',
            'proxy_address' => '10.0.0.4',
            'port' => 9004,
            'is_valid' => true,
            'country_code' => 'GR',
            'city_name' => 'Athens',
            'asn_name' => 'Test ASN',
            'asn_number' => '9999',
        ]);

        $data = app(WebShare::class)->getRandomProxyCredentials();

        $this->assertSame('10.0.0.4:9004', $data['address']);
        $this->assertSame('10.0.0.4', $data['ip']);
        $this->assertSame(9004, $data['port']);
        $this->assertSame('my-user', $data['username']);
        $this->assertSame('my-pass', $data['password']);
        $this->assertSame('http://my-user:my-pass@10.0.0.4:9004', $data['proxy_url']);
    }

    public function test_it_throws_when_no_valid_proxy_exists(): void
    {
        Proxy::query()->create([
            'proxy_id' => 'proxy-invalid',
            'username' => 'u',
            'password' => 'p',
            'proxy_address' => '10.0.0.3',
            'port' => 9001,
            'is_valid' => false,
            'country_code' => 'GR',
            'city_name' => 'Athens',
            'asn_name' => 'Test ASN',
            'asn_number' => '9999',
        ]);

        $this->expectException(NoValidProxyException::class);
        $this->expectExceptionMessage('No valid proxies found in the configured proxy table.');

        app(WebShare::class)->getRandomProxy();
    }

    public function test_it_throws_when_api_key_is_missing(): void
    {
        config()->set('webshare.api_key', null);

        $this->expectException(MissingApiKeyException::class);

        app(WebShare::class)->getProxyList(1);
    }

    public function test_it_url_encodes_credentials_in_proxy_url(): void
    {
        Proxy::query()->create([
            'proxy_id' => 'proxy-encoded',
            'username' => 'user@name',
            'password' => 'p@ss:word',
            'proxy_address' => '10.0.0.9',
            'port' => 8081,
            'is_valid' => true,
            'country_code' => 'GR',
            'city_name' => 'Athens',
            'asn_name' => 'Test ASN',
            'asn_number' => '9999',
        ]);

        $url = app(WebShare::class)->getRandomProxyUrl();

        $this->assertSame('http://user%40name:p%40ss%3Aword@10.0.0.9:8081', $url);
    }

    public function test_it_updates_existing_proxy_instead_of_creating_duplicate(): void
    {
        Proxy::query()->create([
            'proxy_id' => 'proxy-existing',
            'username' => 'old-user',
            'password' => 'old-pass',
            'proxy_address' => '11.11.11.11',
            'port' => 7000,
            'is_valid' => false,
            'country_code' => 'US',
            'city_name' => 'Old City',
            'asn_name' => 'Old ASN',
            'asn_number' => '111',
        ]);

        Http::fake([
            'https://proxy.webshare.io/api/v2/proxy/list*' => Http::response([
                'results' => [
                    [
                        'id' => 'proxy-existing',
                        'username' => 'new-user',
                        'password' => 'new-pass',
                        'proxy_address' => '22.22.22.22',
                        'port' => 8000,
                        'valid' => true,
                        'country_code' => 'GR',
                        'city_name' => 'Athens',
                        'asn_name' => 'New ASN',
                        'asn_number' => '222',
                    ],
                ],
            ], 200),
        ]);

        app(WebShare::class)->updateProxyList();

        $this->assertSame(1, Proxy::query()->where('proxy_id', 'proxy-existing')->count());
        $this->assertDatabaseHas($this->proxyTable(), [
            'proxy_id' => 'proxy-existing',
            'username' => 'new-user',
            'proxy_address' => '22.22.22.22',
            'port' => 8000,
            'is_valid' => 1,
        ]);
    }

    public function test_it_paginates_proxy_list_until_all_results_are_fetched(): void
    {
        Http::fake([
            'https://proxy.webshare.io/api/v2/proxy/list*' => function ($request) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
                $page = (int) ($query['page'] ?? 1);
                $pageSize = (int) ($query['page_size'] ?? 100);

                $this->assertSame(3, $pageSize);

                if ($page === 1) {
                    return Http::response([
                        'count' => 5,
                        'next' => 'https://proxy.webshare.io/api/v2/proxy/list/?mode=direct&page=2&page_size=3',
                        'previous' => null,
                        'results' => [
                            [
                                'id' => 'proxy-p1-a',
                                'username' => 'u1',
                                'password' => 'p1',
                                'proxy_address' => '10.0.0.1',
                                'port' => 8001,
                                'valid' => true,
                                'country_code' => 'US',
                                'city_name' => 'A',
                                'asn_name' => 'ASN1',
                                'asn_number' => '1',
                            ],
                            [
                                'id' => 'proxy-p1-b',
                                'username' => 'u2',
                                'password' => 'p2',
                                'proxy_address' => '10.0.0.2',
                                'port' => 8002,
                                'valid' => true,
                                'country_code' => 'US',
                                'city_name' => 'B',
                                'asn_name' => 'ASN2',
                                'asn_number' => '2',
                            ],
                            [
                                'id' => 'proxy-p1-c',
                                'username' => 'u3',
                                'password' => 'p3',
                                'proxy_address' => '10.0.0.3',
                                'port' => 8003,
                                'valid' => true,
                                'country_code' => 'US',
                                'city_name' => 'C',
                                'asn_name' => 'ASN3',
                                'asn_number' => '3',
                            ],
                        ],
                    ], 200);
                }

                return Http::response([
                    'count' => 5,
                    'next' => null,
                    'previous' => 'https://proxy.webshare.io/api/v2/proxy/list/?mode=direct&page=1&page_size=3',
                    'results' => [
                        [
                            'id' => 'proxy-p2-a',
                            'username' => 'u4',
                            'password' => 'p4',
                            'proxy_address' => '10.0.0.4',
                            'port' => 8004,
                            'valid' => true,
                            'country_code' => 'US',
                            'city_name' => 'D',
                            'asn_name' => 'ASN4',
                            'asn_number' => '4',
                        ],
                        [
                            'id' => 'proxy-p2-b',
                            'username' => 'u5',
                            'password' => 'p5',
                            'proxy_address' => '10.0.0.5',
                            'port' => 8005,
                            'valid' => true,
                            'country_code' => 'US',
                            'city_name' => 'E',
                            'asn_name' => 'ASN5',
                            'asn_number' => '5',
                        ],
                    ],
                ], 200);
            },
        ]);

        app(WebShare::class)->updateProxyList(3);

        $this->assertSame(5, Proxy::query()->count());

        Http::assertSentCount(2);
        $this->assertDatabaseHas($this->proxyTable(), ['proxy_id' => 'proxy-p1-a']);
        $this->assertDatabaseHas($this->proxyTable(), ['proxy_id' => 'proxy-p2-b']);
    }

    public function test_it_rejects_insecure_api_urls(): void
    {
        config()->set('webshare.base_url', 'http://proxy.webshare.io/api/v2/');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WebShare API URL must be a valid HTTPS URL.');

        app(WebShare::class)->getProxyList(1);
    }

    public function test_it_rejects_custom_api_urls_unless_enabled(): void
    {
        config()->set('webshare.base_url', 'https://example.test/api/');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom WebShare API URLs require webshare.allow_custom_base_url to be enabled.');

        app(WebShare::class)->getProxyList(1);
    }

    public function test_it_allows_custom_api_urls_when_enabled(): void
    {
        config()->set('webshare.base_url', 'https://example.test/api/');
        config()->set('webshare.allow_custom_base_url', true);

        Http::fake([
            'https://example.test/api/proxy/list*' => Http::response([
                'count' => 0,
                'next' => null,
                'previous' => null,
                'results' => [],
            ], 200),
        ]);

        $data = app(WebShare::class)->getProxyList(1);

        $this->assertSame([], $data['results']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://example.test/api/proxy/list'));
    }

    public function test_it_rejects_malformed_proxy_list_responses(): void
    {
        Http::fake([
            'https://proxy.webshare.io/api/v2/proxy/list*' => Http::response([
                'count' => 1,
            ], 200),
        ]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid WebShare proxy list response.');

        app(WebShare::class)->getProxyList(1);
    }
}
