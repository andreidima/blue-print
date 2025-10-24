<?php

namespace Tests\Feature\WooCommerce;

use App\Models\WooCommerce\Customer;
use App\Models\WooCommerce\Order;
use App\Models\WooCommerce\SyncState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_synchronises_orders_from_woocommerce(): void
    {
        config([
            'woocommerce.url' => 'https://example.com',
            'woocommerce.consumer_key' => 'ck_test',
            'woocommerce.consumer_secret' => 'cs_test',
        ]);

        Http::fake([
            'https://example.com/wp-json/wc/v3/orders*' => Http::response([
                [
                    'id' => 123,
                    'status' => 'processing',
                    'currency' => 'EUR',
                    'total' => '100.00',
                    'subtotal' => '80.00',
                    'total_tax' => '20.00',
                    'shipping_total' => '0.00',
                    'discount_total' => '0.00',
                    'payment_method' => 'bacs',
                    'payment_method_title' => 'Bank transfer',
                    'date_created' => '2024-01-01T10:00:00',
                    'date_modified' => '2024-01-01T11:00:00',
                    'customer_id' => 99,
                    'billing' => [
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john@example.com',
                        'phone' => '0123',
                        'address_1' => 'Street 1',
                        'city' => 'City',
                        'postcode' => '12345',
                        'country' => 'RO',
                    ],
                    'shipping' => [
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'address_1' => 'Street 1',
                        'city' => 'City',
                        'postcode' => '12345',
                        'country' => 'RO',
                    ],
                    'line_items' => [
                        [
                            'id' => 555,
                            'name' => 'Sample product',
                            'product_id' => 44,
                            'quantity' => 2,
                            'price' => '50.00',
                            'subtotal' => '100.00',
                            'total' => '100.00',
                            'meta_data' => [],
                            'taxes' => [],
                        ],
                    ],
                ],
            ], 200, ['X-WP-TotalPages' => 1]),
        ]);

        $result = Artisan::call('woocommerce:sync-orders');

        $this->assertSame(0, $result);
        $this->assertDatabaseCount('wc_orders', 1);
        $this->assertDatabaseHas('wc_orders', [
            'woocommerce_id' => 123,
            'status' => 'processing',
            'total' => 100.00,
        ]);
        $this->assertDatabaseHas('wc_order_items', [
            'woocommerce_item_id' => 555,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('wc_order_addresses', [
            'type' => 'billing',
            'city' => 'City',
        ]);

        $this->assertDatabaseCount('wc_customers', 1);
        $this->assertTrue(Customer::first()->orders()->where('woocommerce_id', 123)->exists());
        $this->assertNotNull(Order::first()->date_modified);
    }

    public function test_it_updates_last_synced_at_when_no_orders_are_returned(): void
    {
        config([
            'woocommerce.url' => 'https://example.com',
            'woocommerce.consumer_key' => 'ck_test',
            'woocommerce.consumer_secret' => 'cs_test',
        ]);

        SyncState::create([
            'key' => 'orders.last_synced_at',
            'value' => '2024-01-01T00:00:00Z',
        ]);

        Carbon::setTestNow(Carbon::parse('2024-02-01T12:00:00Z'));

        Http::fake([
            'https://example.com/wp-json/wc/v3/orders*' => Http::response([], 200, ['X-WP-TotalPages' => 1]),
        ]);

        $result = Artisan::call('woocommerce:sync-orders');

        $this->assertSame(0, $result);
        $this->assertDatabaseHas('wc_sync_states', [
            'key' => 'orders.last_synced_at',
            'value' => Carbon::now()->utc()->toIso8601String(),
        ]);

        Carbon::setTestNow();
    }
}
