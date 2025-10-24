<?php

namespace App\Services\WooCommerce;

use App\Models\WooCommerce\Order;
use App\Services\WooCommerce\Exceptions\WooCommerceRequestException;

class OrderStatusService
{
    public function __construct(
        private readonly Client $client,
        private readonly OrderSynchronizer $synchronizer
    ) {
    }

    /**
     * @throws WooCommerceRequestException
     */
    public function updateStatus(Order $order, string $status): Order
    {
        $payload = $this->client->updateOrder($order->woocommerce_id, [
            'status' => $status,
        ]);

        if (! is_array($payload)) {
            $order->forceFill(['status' => $status])->save();

            return $order->refresh();
        }

        if (! array_key_exists('id', $payload)) {
            $payload['id'] = $order->woocommerce_id;
        }

        return $this->synchronizer->sync($payload);
    }
}
