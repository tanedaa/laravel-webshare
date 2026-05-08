<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use RuntimeException;
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

        $this->assertDatabaseHas('proxies', [
            'proxy_id' => 'proxy-1',
            'proxy_address' => '192.168.1.10',
            'port' => 8080,
            'is_valid' => 1,
        ]);
    }

    public function test_it_returns_random_proxy_data(): void
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

        $this->assertSame('10.0.0.2:9000', $data['address']);
        $this->assertSame('10.0.0.2', $data['ip']);
        $this->assertSame(9000, $data['port']);
        $this->assertSame('my-user', $data['username']);
        $this->assertSame('my-pass', $data['password']);
        $this->assertSame('http://my-user:my-pass@10.0.0.2:9000', $data['proxy_url']);
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No valid proxies found in the proxies table.');

        app(WebShare::class)->getRandomProxy();
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
        $this->assertDatabaseHas('proxies', [
            'proxy_id' => 'proxy-existing',
            'username' => 'new-user',
            'proxy_address' => '22.22.22.22',
            'port' => 8000,
            'is_valid' => 1,
        ]);
    }
}
