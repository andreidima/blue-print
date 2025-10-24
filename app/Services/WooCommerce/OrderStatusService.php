<?php

namespace App\Services\WooCommerce;

use App\Models\WooCommerce\Order;
use App\Services\WooCommerce\Exceptions\WooCommerceRequestException;

class OrderStatusService
{
    private const DEFAULT_STATUSES = [
        'auto-draft',
        'cancelled',
        'completed',
        'draft',
        'failed',
        'on-hold',
        'pending',
        'processing',
        'refunded',
        'trash',
    ];

    public function __construct(
        private readonly Client $client,
        private readonly OrderSynchronizer $synchronizer,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function allowedStatuses(): array
    {
        try {
            $statuses = $this->client->getOrderStatuses();

            $normalized = array_values(array_filter(
                array_map(
                    static fn ($status) => is_string($status) ? trim($status) : null,
                    array_keys($statuses)
                ),
                static fn ($status) => ! empty($status)
            ));

            if (! empty($normalized)) {
                return $normalized;
            }
        } catch (WooCommerceRequestException) {
            // If we cannot reach WooCommerce we fall back to locally known statuses.
        }

        $localStatuses = Order::query()
            ->select('status')
            ->distinct()
            ->pluck('status')
            ->filter(fn ($status) => is_string($status) && $status !== '')
            ->values()
            ->all();

        if (! empty($localStatuses)) {
            return $localStatuses;
        }

        return self::DEFAULT_STATUSES;
    }

    public function updateStatus(Order $order, string $status): Order
    {
        $woocommerceId = (int) $order->woocommerce_id;

        if ($woocommerceId <= 0) {
            $order->forceFill(['status' => $status])->save();

            return $order->refresh();
        }

        $payload = $this->client->updateOrder($woocommerceId, ['status' => $status]);

        if (! is_array($payload) || empty($payload)) {
            $order->forceFill(['status' => $status])->save();

            return $order->refresh();
        }

        return $this->synchronizer->sync($payload);
    }
}
