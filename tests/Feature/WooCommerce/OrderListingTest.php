<?php

namespace Tests\Feature\WooCommerce;

use App\Models\User;
use App\Models\WooCommerce\Customer;
use App\Models\WooCommerce\Order;
use App\Models\WooCommerce\OrderAddress;
use App\Models\WooCommerce\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_orders_and_applies_filters(): void
    {
        $user = User::factory()->create();

        $firstCustomer = Customer::create([
            'woocommerce_id' => 1,
            'email' => 'alice@example.com',
            'first_name' => 'Alice',
            'last_name' => 'Ionescu',
            'phone' => '0711111111',
        ]);

        $secondCustomer = Customer::create([
            'woocommerce_id' => 2,
            'email' => 'bob@example.com',
            'first_name' => 'Bob',
            'last_name' => 'Popescu',
            'phone' => '0722222222',
        ]);

        $firstOrder = Order::create([
            'woocommerce_id' => 1001,
            'wc_customer_id' => $firstCustomer->id,
            'status' => 'processing',
            'currency' => 'RON',
            'total' => 150.50,
            'subtotal' => 150.50,
            'total_tax' => 0,
            'shipping_total' => 0,
            'discount_total' => 0,
            'date_created' => Carbon::parse('2024-02-15 10:30:00'),
            'meta' => ['number' => 'WC-1001'],
        ]);

        $secondOrder = Order::create([
            'woocommerce_id' => 2002,
            'wc_customer_id' => $secondCustomer->id,
            'status' => 'completed',
            'currency' => 'RON',
            'total' => 250,
            'subtotal' => 250,
            'total_tax' => 0,
            'shipping_total' => 0,
            'discount_total' => 0,
            'date_created' => Carbon::parse('2024-03-10 08:15:00'),
            'meta' => ['number' => 'WC-2002'],
        ]);

        OrderAddress::create([
            'wc_order_id' => $firstOrder->id,
            'type' => 'billing',
            'first_name' => 'Alice',
            'last_name' => 'Ionescu',
            'email' => 'alice@example.com',
            'phone' => '0711111111',
        ]);

        OrderAddress::create([
            'wc_order_id' => $secondOrder->id,
            'type' => 'billing',
            'first_name' => 'Bob',
            'last_name' => 'Popescu',
            'email' => 'bob@example.com',
            'phone' => '0722222222',
        ]);

        OrderItem::create([
            'wc_order_id' => $firstOrder->id,
            'woocommerce_item_id' => 501,
            'product_id' => 11,
            'name' => 'Produs A',
            'quantity' => 2,
            'price' => 75.25,
            'subtotal' => 150.50,
            'total' => 150.50,
        ]);

        OrderItem::create([
            'wc_order_id' => $secondOrder->id,
            'woocommerce_item_id' => 502,
            'product_id' => 12,
            'name' => 'Produs B',
            'quantity' => 3,
            'price' => 83.33,
            'subtotal' => 249.99,
            'total' => 249.99,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('woocommerce.orders.index'));

        $response->assertOk();
        $response->assertSee('WC-1001');
        $response->assertSee('WC-2002');
        $response->assertSee('Processing');
        $response->assertSee('Completed');
        $response->assertSee('0711111111');

        $response = $this->get(route('woocommerce.orders.index', ['status' => 'completed']));
        $response->assertOk();
        $response->assertSee('WC-2002');
        $response->assertDontSee('WC-1001');

        $response = $this->get(route('woocommerce.orders.index', [
            'searchIntervalData' => '2024-02-01,2024-02-28',
        ]));
        $response->assertOk();
        $response->assertSee('WC-1001');
        $response->assertDontSee('WC-2002');
    }
}
