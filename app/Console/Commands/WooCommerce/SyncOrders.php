<?php

namespace App\Console\Commands\WooCommerce;

use App\Models\WooCommerce\SyncState;
use App\Services\WooCommerce\Client;
use App\Services\WooCommerce\Exceptions\WooCommerceRequestException;
use App\Services\WooCommerce\OrderSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncOrders extends Command
{
    protected $signature = 'woocommerce:sync-orders {--since=}';

    protected $description = 'Synchronise WooCommerce orders into the local database';

    public function __construct(protected Client $client, protected OrderSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (empty(config('woocommerce.url')) || empty(config('woocommerce.consumer_key')) || empty(config('woocommerce.consumer_secret'))) {
            $this->error('WooCommerce configuration is incomplete. Please check config/woocommerce.php or your environment variables.');

            return self::FAILURE;
        }

        $sinceOption = $this->option('since');
        $lastSyncedAt = $this->getLastSyncedAt();
        $since = $sinceOption ? Carbon::parse($sinceOption) : $lastSyncedAt;

        $this->info(sprintf('Fetching WooCommerce orders updated since %s', $since?->toIso8601String() ?? 'the beginning'));

        try {
            $orders = $this->client->getOrdersUpdatedSince($since);
        } catch (WooCommerceRequestException $exception) {
            Log::error('WooCommerce order sync failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->error('Failed to fetch orders from WooCommerce. See logs for details.');

            return self::FAILURE;
        }

        $processed = 0;
        $latestModification = $since;

        foreach ($orders as $orderPayload) {
            $order = $this->synchronizer->sync($orderPayload);
            $processed++;
            $modified = $order->date_modified ?? $order->date_created;

            if ($modified && ($latestModification === null || $modified->greaterThan($latestModification))) {
                $latestModification = $modified;
            }
        }

        if ($latestModification) {
            $this->setLastSyncedAt($latestModification);
        }

        $this->info(sprintf('Processed %d orders.', $processed));

        return self::SUCCESS;
    }

    protected function getLastSyncedAt(): ?Carbon
    {
        $state = SyncState::query()->where('key', 'orders.last_synced_at')->first();

        if (! $state) {
            return null;
        }

        return Carbon::parse($state->value);
    }

    protected function setLastSyncedAt(Carbon $timestamp): void
    {
        SyncState::updateOrCreate(
            ['key' => 'orders.last_synced_at'],
            ['value' => $timestamp->utc()->toIso8601String()]
        );
    }
}
