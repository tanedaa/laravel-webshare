<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Tanedaa\LaravelWebShare\Exceptions\NoValidProxyException;
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
        $this->assertTrue(PendingRequest::hasMacro('webshare'));
    }

    public function test_http_macro_throws_when_no_valid_proxy_exists(): void
    {
        $this->expectException(NoValidProxyException::class);

        Http::webshare();
    }

    public function test_http_webshare_then_as_form_keeps_proxy_option(): void
    {
        Proxy::query()->create([
            'proxy_id' => 'proxy-chain-1',
            'username' => 'user1',
            'password' => 'pass1',
            'proxy_address' => '8.8.8.8',
            'port' => 3000,
            'is_valid' => true,
            'country_code' => 'US',
            'city_name' => 'City',
            'asn_name' => 'ASN',
            'asn_number' => '1',
        ]);

        $request = Http::webshare()->asForm();

        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty('options');
        $property->setAccessible(true);
        $options = $property->getValue($request);

        $this->assertSame('http://user1:pass1@8.8.8.8:3000', $options['proxy']);
        $this->assertSame('application/x-www-form-urlencoded', $options['headers']['Content-Type']);
    }

    public function test_http_as_form_then_webshare_keeps_form_and_proxy_options(): void
    {
        Proxy::query()->create([
            'proxy_id' => 'proxy-chain-2',
            'username' => 'user2',
            'password' => 'pass2',
            'proxy_address' => '9.9.9.9',
            'port' => 4000,
            'is_valid' => true,
            'country_code' => 'US',
            'city_name' => 'City',
            'asn_name' => 'ASN',
            'asn_number' => '2',
        ]);

        $request = Http::asForm()->webshare();

        $reflection = new \ReflectionClass($request);
        $property = $reflection->getProperty('options');
        $property->setAccessible(true);
        $options = $property->getValue($request);

        $this->assertSame('http://user2:pass2@9.9.9.9:4000', $options['proxy']);
        $this->assertSame('application/x-www-form-urlencoded', $options['headers']['Content-Type']);
    }
}
