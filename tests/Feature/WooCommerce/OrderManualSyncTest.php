<?php

namespace Tests\Feature\WooCommerce;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OrderManualSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_trigger_manual_sync(): void
    {
        config([
            'woocommerce.url' => 'https://example.com',
            'woocommerce.consumer_key' => 'ck_test',
            'woocommerce.consumer_secret' => 'cs_test',
        ]);

        $user = User::factory()->create();

        Artisan::shouldReceive('call')
            ->once()
            ->with('woocommerce:sync-orders')
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->andReturn("Processed 3 orders.\nDone.");

        $this->actingAs($user);

        $response = $this->from(route('woocommerce.orders.index'))
            ->post(route('woocommerce.orders.sync'));

        $response->assertRedirect(route('woocommerce.orders.index'));
        $response->assertSessionHas('success', function ($value) {
            return is_string($value) && str_contains($value, 'Au fost actualizate 3 comenzi.');
        });

        $this->assertDatabaseHas('wc_sync_states', [
            'key' => 'orders.last_synced_at',
        ]);
    }
}
