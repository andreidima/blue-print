<?php

namespace App\Console\Commands\WooCommerce;

use App\Models\WooCommerce\SyncState;
use App\Services\WooCommerce\Client;
use App\Services\WooCommerce\Exceptions\WooCommerceRequestException;
use App\Services\WooCommerce\OrderSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            Log::channel('woocommerce')->warning('WooCommerce order sync aborted due to missing configuration');

            $this->error('WooCommerce configuration is incomplete. Please check config/woocommerce.php or your environment variables.');

            return self::FAILURE;
        }

        $sinceOption = $this->option('since');
        $lastSyncedAt = $this->getLastSyncedAt();
        $since = $sinceOption ? Carbon::parse($sinceOption) : $lastSyncedAt;

        $logContext = [
            'since_option' => $sinceOption,
            'since_resolved' => $since?->toIso8601String(),
            'php_binary' => PHP_BINARY,
            'php_sapi' => PHP_SAPI,
            'artisan_entry' => $_SERVER['argv'][0] ?? null,
        ];

        $this->line(sprintf('Running WooCommerce sync using PHP binary: %s (%s)', PHP_BINARY, PHP_SAPI));

        Log::channel('woocommerce')->info('Starting WooCommerce order sync run', $logContext);

        $this->info(sprintf('Fetching WooCommerce orders updated since %s', $since?->toIso8601String() ?? 'the beginning'));

        try {
            $orders = $this->client->getOrdersUpdatedSince($since);
        } catch (WooCommerceRequestException $exception) {
            Log::channel('woocommerce')->error('WooCommerce order sync failed during API request', [
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $this->error('Failed to fetch orders from WooCommerce. See logs for details.');

            return self::FAILURE;
        }

        $processed = 0;
        $latestModification = $since;

        try {
            foreach ($orders as $orderPayload) {
                $order = $this->synchronizer->sync($orderPayload);
                $processed++;
                $modified = $order->date_modified ?? $order->date_created;

                if ($modified && ($latestModification === null || $modified->greaterThan($latestModification))) {
                    $latestModification = $modified;
                }
            }
        } catch (Throwable $exception) {
            Log::channel('woocommerce')->error('WooCommerce order sync failed during persistence', [
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $this->error('Failed to save orders locally. See logs for details.');

            return self::FAILURE;
        }

        $timestampToPersist = $latestModification;

        if ($processed === 0 || $timestampToPersist === null) {
            $timestampToPersist = Carbon::now();
        }

        $this->setLastSyncedAt($timestampToPersist);

        Log::channel('woocommerce')->info('WooCommerce order sync completed', array_merge($logContext, [
            'processed' => $processed,
            'orders_received' => is_countable($orders) ? count($orders) : null,
            'last_synced_at' => $timestampToPersist?->toIso8601String(),
        ]));

        if ($processed === 0) {
            $this->info('No WooCommerce orders required updates.');
        } else {
            $this->info(sprintf('Processed %d orders.', $processed));
        }

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
