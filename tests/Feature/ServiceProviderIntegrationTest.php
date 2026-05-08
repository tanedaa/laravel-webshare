<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Tanedaa\LaravelWebShare\Facades\WebShare as WebShareFacade;
use Tanedaa\LaravelWebShare\Models\Proxy;
use Tanedaa\LaravelWebShare\Services\WebShare;
use Tests\TestCase;

class ServiceProviderIntegrationTest extends TestCase
{
    public function test_it_binds_webshare_service_in_container(): void
    {
        $this->assertInstanceOf(WebShare::class, app('webshare'));
        $this->assertInstanceOf(WebShare::class, app(WebShare::class));
    }

    public function test_facade_resolves_service_and_returns_proxy_url(): void
    {
        Proxy::query()->create([
            'proxy_id' => 'proxy-facade',
            'username' => 'facade-user',
            'password' => 'facade-pass',
            'proxy_address' => '127.0.0.1',
            'port' => 8088,
            'is_valid' => true,
            'country_code' => 'US',
            'city_name' => 'Austin',
            'asn_name' => 'ASN',
            'asn_number' => '100',
        ]);

        $url = WebShareFacade::getRandomProxyUrl();

        $this->assertSame('http://facade-user:facade-pass@127.0.0.1:8088', $url);
    }

    public function test_http_macro_returns_pending_request_with_proxy_option(): void
    {
        Proxy::query()->create([
            'proxy_id' => 'proxy-macro',
            'username' => 'macro-user',
            'password' => 'macro-pass',
            'proxy_address' => '1.2.3.4',
            'port' => 3128,
            'is_valid' => true,
            'country_code' => 'US',
            'city_name' => 'Miami',
            'asn_name' => 'ASN',
            'asn_number' => '200',
        ]);

        $request = Http::webshare();

        $this->assertInstanceOf(PendingRequest::class, $request);

        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty('options');
        $property->setAccessible(true);
        $options = $property->getValue($request);

        $this->assertArrayHasKey('proxy', $options);
        $this->assertSame('http://macro-user:macro-pass@1.2.3.4:3128', $options['proxy']);
    }

    public function test_http_factory_macro_is_registered(): void
    {
        $this->assertTrue(HttpFactory::hasMacro('webshare'));
    }
}
