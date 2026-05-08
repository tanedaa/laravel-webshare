<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tanedaa\LaravelWebShare\Models\Proxy;
use Tests\TestCase;

class UpdateWebShareProxiesCommandTest extends TestCase
{
    public function test_it_syncs_proxies_via_artisan_command(): void
    {
        Http::fake([
            'https://proxy.webshare.io/api/v2/proxy/list*' => Http::response([
                'count' => 2,
                'next' => null,
                'previous' => null,
                'results' => [
                    [
                        'id' => 'proxy-cmd-1',
                        'username' => 'u1',
                        'password' => 'p1',
                        'proxy_address' => '55.55.55.1',
                        'port' => 8081,
                        'valid' => true,
                        'country_code' => 'US',
                        'city_name' => 'A',
                        'asn_name' => 'ASN1',
                        'asn_number' => '111',
                    ],
                    [
                        'id' => 'proxy-cmd-2',
                        'username' => 'u2',
                        'password' => 'p2',
                        'proxy_address' => '55.55.55.2',
                        'port' => 8082,
                        'valid' => true,
                        'country_code' => 'US',
                        'city_name' => 'B',
                        'asn_name' => 'ASN2',
                        'asn_number' => '222',
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('webshare:update-proxies', ['--page-size' => 2])
            ->expectsOutput('WebShare proxy sync complete. Processed 2 proxy record(s).')
            ->assertSuccessful();

        $this->assertSame(2, Proxy::query()->count());
        $this->assertDatabaseHas($this->proxyTable(), ['proxy_id' => 'proxy-cmd-1']);
        $this->assertDatabaseHas($this->proxyTable(), ['proxy_id' => 'proxy-cmd-2']);
    }

    public function test_it_fails_with_helpful_message_when_api_key_is_missing(): void
    {
        config()->set('webshare.api_key', null);

        $this->artisan('webshare:update-proxies')
            ->expectsOutputToContain('Missing WebShare API key')
            ->assertFailed();
    }
}
