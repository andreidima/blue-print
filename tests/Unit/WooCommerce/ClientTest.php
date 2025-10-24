<?php

namespace Tests\Unit\WooCommerce;

use App\Services\WooCommerce\Client;
use App\Services\WooCommerce\Exceptions\WooCommerceRequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientTest extends TestCase
{
    public function test_it_updates_product_stock_when_product_exists(): void
    {
        Http::fake([
            'https://example.test/wp-json/wc/v3/products*' => Http::response([
                [
                    'id' => 123,
                    'sku' => 'SKU-001',
                ],
            ], 200),
            'https://example.test/wp-json/wc/v3/products/123' => Http::response([
                'id' => 123,
                'stock_quantity' => 10,
            ], 200),
        ]);

        $client = new Client('https://example.test', 'ck', 'cs', 'wc/v3');

        $result = $client->updateProductStock('SKU-001', 10);

        $this->assertTrue($result);

        Http::assertSentCount(2);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://example.test/wp-json/wc/v3/products?sku=SKU-001';
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && $request->url() === 'https://example.test/wp-json/wc/v3/products/123'
                && $request['manage_stock'] === true
                && $request['stock_quantity'] === 10
                && $request['stock_status'] === 'instock';
        });
    }

    public function test_it_returns_false_when_product_is_missing(): void
    {
        Http::fake([
            'https://example.test/wp-json/wc/v3/products*' => Http::response([], 200),
        ]);

        $client = new Client('https://example.test', 'ck', 'cs', 'wc/v3');

        $result = $client->updateProductStock('MISSING', 5);

        $this->assertFalse($result);

        Http::assertSentCount(1);
    }

    public function test_it_throws_exception_when_request_fails(): void
    {
        Http::fake([
            'https://example.test/wp-json/wc/v3/products*' => Http::response('error', 500),
        ]);

        $client = new Client('https://example.test', 'ck', 'cs', 'wc/v3');

        $this->expectException(WooCommerceRequestException::class);

        $client->updateProductStock('SKU-ERR', 3);
    }
}
