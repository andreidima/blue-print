<?php

namespace Tests\Unit\WooCommerce;

use App\Models\WooCommerce\Order;
use App\Services\WooCommerce\Client;
use App\Services\WooCommerce\Exceptions\WooCommerceRequestException;
use App\Services\WooCommerce\OrderStatusService;
use App\Services\WooCommerce\OrderSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class OrderStatusServiceTest extends TestCase
{
    use RefreshDatabase;
    use MockeryPHPUnitIntegration;

    public function test_it_synchronizes_order_when_remote_update_succeeds(): void
    {
        $order = Order::create([
            'woocommerce_id' => 4321,
            'status' => 'processing',
            'currency' => 'RON',
            'total' => 120,
            'subtotal' => 100,
            'total_tax' => 20,
            'shipping_total' => 0,
            'discount_total' => 0,
            'payment_method' => 'card',
            'payment_method_title' => 'Card',
            'meta' => [],
        ]);

        $client = Mockery::mock(Client::class);
        $synchronizer = Mockery::mock(OrderSynchronizer::class);

        $service = new OrderStatusService($client, $synchronizer);

        $payload = [
            'id' => 4321,
            'status' => 'completed',
            'line_items' => [],
            'billing' => [],
            'shipping' => [],
        ];

        $client->shouldReceive('updateOrder')
            ->once()
            ->with(4321, ['status' => 'completed'])
            ->andReturn($payload);

        $updatedOrder = clone $order;
        $updatedOrder->status = 'completed';

        $synchronizer->shouldReceive('sync')
            ->once()
            ->with($payload)
            ->andReturn($updatedOrder);

        $result = $service->updateStatus($order, 'completed');

        $this->assertSame('completed', $result->status);
    }

    public function test_it_updates_local_status_when_response_is_not_array(): void
    {
        $order = Order::create([
            'woocommerce_id' => 9999,
            'status' => 'processing',
            'currency' => 'RON',
            'total' => 80,
            'subtotal' => 80,
            'total_tax' => 0,
            'shipping_total' => 0,
            'discount_total' => 0,
            'payment_method' => 'cod',
            'payment_method_title' => 'Ramburs',
            'meta' => [],
        ]);

        $client = Mockery::mock(Client::class);
        $synchronizer = Mockery::mock(OrderSynchronizer::class);

        $service = new OrderStatusService($client, $synchronizer);

        $client->shouldReceive('updateOrder')
            ->once()
            ->with(9999, ['status' => 'completed'])
            ->andReturn('ok');

        $synchronizer->shouldReceive('sync')->never();

        $result = $service->updateStatus($order, 'completed');

        $this->assertSame('completed', $result->status);
        $this->assertDatabaseHas('wc_orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);
    }

    public function test_it_propagates_exceptions_from_client(): void
    {
        $order = Order::create([
            'woocommerce_id' => 6543,
            'status' => 'processing',
            'currency' => 'RON',
            'total' => 50,
            'subtotal' => 50,
            'total_tax' => 0,
            'shipping_total' => 0,
            'discount_total' => 0,
            'payment_method' => 'cod',
            'payment_method_title' => 'Ramburs',
            'meta' => [],
        ]);

        $client = Mockery::mock(Client::class);
        $synchronizer = Mockery::mock(OrderSynchronizer::class);

        $service = new OrderStatusService($client, $synchronizer);

        $client->shouldReceive('updateOrder')
            ->once()
            ->with(6543, ['status' => 'cancelled'])
            ->andThrow(new WooCommerceRequestException('Request failed'));

        $synchronizer->shouldReceive('sync')->never();

        $this->expectException(WooCommerceRequestException::class);

        try {
            $service->updateStatus($order, 'cancelled');
        } finally {
            $this->assertDatabaseHas('wc_orders', [
                'id' => $order->id,
                'status' => 'processing',
            ]);
        }
    }
}
