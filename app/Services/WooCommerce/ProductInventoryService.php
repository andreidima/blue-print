<?php

namespace App\Services\WooCommerce;

use App\Models\Produs;
use App\Services\WooCommerce\Exceptions\WooCommerceRequestException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductInventoryService
{
    public function __construct(
        protected readonly Client $client
    ) {
    }

    public function syncStock(Produs $produs): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $skus = $this->collectSkus($produs);

        if ($skus->isEmpty()) {
            return;
        }

        $stock = max((int) ($produs->cantitate ?? 0), 0);

        foreach ($skus as $sku) {
            try {
                $updated = $this->client->updateProductStock($sku, $stock);

                if (! $updated) {
                    Log::warning('WooCommerce product not found for stock sync.', [
                        'sku' => $sku,
                        'produs_id' => $produs->id,
                    ]);
                }
            } catch (WooCommerceRequestException $exception) {
                Log::error('WooCommerce stock sync failed.', [
                    'sku' => $sku,
                    'produs_id' => $produs->id,
                    'error' => $exception->getMessage(),
                ]);
            } catch (Throwable $exception) {
                Log::error('Unexpected error while syncing WooCommerce stock.', [
                    'sku' => $sku,
                    'produs_id' => $produs->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    protected function collectSkus(Produs $produs): Collection
    {
        if (! $produs->relationLoaded('skuAliases')) {
            $produs->loadMissing('skuAliases');
        }

        $aliases = $produs->skuAliases instanceof EloquentCollection
            ? $produs->skuAliases
            : new EloquentCollection();

        return collect([$produs->sku])
            ->merge($aliases->pluck('sku'))
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();
    }

    protected function isConfigured(): bool
    {
        return filled(config('woocommerce.url'))
            && filled(config('woocommerce.consumer_key'))
            && filled(config('woocommerce.consumer_secret'));
    }
}
