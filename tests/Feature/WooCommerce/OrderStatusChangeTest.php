<?php

namespace Tests\Feature\WooCommerce;

use App\Models\User;
use App\Models\WooCommerce\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderStatusChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_remote_and_local_order_status(): void
    {
        config([
            'woocommerce.url' => 'https://example-store.test',
            'woocommerce.consumer_key' => 'ck_test',
            'woocommerce.consumer_secret' => 'cs_test',
            'woocommerce.version' => 'wc/v3',
        ]);

        $user = User::factory()->create();

        $order = Order::create([
            'woocommerce_id' => 1234,
            'status' => 'processing',
            'currency' => 'RON',
            'total' => 100,
            'subtotal' => 80,
            'total_tax' => 20,
            'shipping_total' => 0,
            'discount_total' => 0,
            'payment_method' => 'cod',
            'payment_method_title' => 'Ramburs',
            'meta' => [],
        ]);

        Http::fake([
            'https://example-store.test/wp-json/wc/v3/orders/*' => Http::response([
                'id' => 1234,
                'status' => 'completed',
                'currency' => 'RON',
                'total' => '100.00',
                'line_items' => [],
                'billing' => [],
                'shipping' => [],
            ]),
        ]);

        $this->actingAs($user);

        $response = $this->from(route('woocommerce.orders.index'))
            ->patch(route('woocommerce.orders.status-change', $order), [
                'status' => 'completed',
            ]);

        $response->assertRedirect(route('woocommerce.orders.index'));
        $response->assertSessionHas('success', function ($value) {
            return is_string($value)
                && str_contains($value, 'Statusul comenzii WooCommerce a fost actualizat cu succes.');
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/orders/1234')
                && $request['status'] === 'completed';
        });

        $this->assertDatabaseHas('wc_orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);
    }
}
