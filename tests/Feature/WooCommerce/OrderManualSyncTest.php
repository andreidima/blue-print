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
            return is_string($value)
                && str_contains($value, 'Sincronizarea WooCommerce s-a încheiat cu succes.')
                && str_contains($value, 'Au fost actualizate 3 comenzi.');
        });

        $this->assertDatabaseHas('wc_sync_states', [
            'key' => 'orders.last_synced_at',
        ]);
    }

    public function test_it_displays_user_friendly_message_when_sync_fails(): void
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
            ->andReturn(1);

        Artisan::shouldReceive('output')
            ->andReturn("Running WooCommerce sync using PHP\nFetching WooCommerce orders updated since 2025-10-24T09:14:02+00:00\nFailed to save orders locally. See logs for details.");

        $this->actingAs($user);

        $response = $this->from(route('woocommerce.orders.index'))
            ->post(route('woocommerce.orders.sync'));

        $response->assertRedirect(route('woocommerce.orders.index'));

        $response->assertSessionHas('error', function ($value) {
            return is_string($value)
                && str_contains($value, 'Sincronizarea WooCommerce nu a reușit.')
                && str_contains($value, 'Comenzile nu au putut fi salvate în aplicație. Încearcă din nou sau contactează un administrator.')
                && str_contains($value, 'Vezi detaliile tehnice');
        });
    }
}
