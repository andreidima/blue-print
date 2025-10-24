<?php

namespace App\Services\WooCommerce;

use App\Models\WooCommerce\Order;
use App\Services\WooCommerce\Exceptions\WooCommerceRequestException;

class OrderStatusService
{
    public function __construct(protected Client $client)
    {
    }

    /**
     * @throws WooCommerceRequestException
     */
    public function update(Order $order, string $status): Order
    {
        try {
            $this->client->updateOrderStatus($order->woocommerce_id, $status);
        } catch (WooCommerceRequestException $exception) {
            report($exception);

            throw $exception;
        }

        $order->forceFill([
            'status' => $status,
        ])->save();

        return $order->refresh();
    }
}
