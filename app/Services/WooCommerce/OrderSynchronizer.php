<?php

namespace App\Services\WooCommerce;

use App\Models\WooCommerce\Customer;
use App\Models\WooCommerce\Order;
use App\Models\WooCommerce\OrderAddress;
use App\Models\WooCommerce\OrderItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderSynchronizer
{
    public function sync(array $orderPayload): Order
    {
        return DB::transaction(function () use ($orderPayload) {
            $customer = $this->syncCustomer($orderPayload);

            $order = Order::updateOrCreate(
                ['woocommerce_id' => $orderPayload['id']],
                [
                    'wc_customer_id' => $customer?->id,
                    'status' => $orderPayload['status'] ?? 'pending',
                    'currency' => $orderPayload['currency'] ?? null,
                    'total' => Arr::get($orderPayload, 'total', 0),
                    'subtotal' => Arr::get($orderPayload, 'subtotal', 0),
                    'total_tax' => Arr::get($orderPayload, 'total_tax', 0),
                    'shipping_total' => Arr::get($orderPayload, 'shipping_total', 0),
                    'discount_total' => Arr::get($orderPayload, 'discount_total', 0),
                    'payment_method' => Arr::get($orderPayload, 'payment_method'),
                    'payment_method_title' => Arr::get($orderPayload, 'payment_method_title'),
                    'date_created' => $this->parseDate(Arr::get($orderPayload, 'date_created_gmt', Arr::get($orderPayload, 'date_created'))),
                    'date_modified' => $this->parseDate(Arr::get($orderPayload, 'date_modified_gmt', Arr::get($orderPayload, 'date_modified'))),
                    'meta' => Arr::except($orderPayload, [
                        'line_items',
                        'billing',
                        'shipping',
                        'fee_lines',
                        'shipping_lines',
                        'coupon_lines',
                        'meta_data',
                        'refunds',
                    ]),
                ]
            );

            $this->syncAddresses($order, $orderPayload);
            $this->syncItems($order, $orderPayload);

            return $order;
        });
    }

    protected function syncCustomer(array $orderPayload): ?Customer
    {
        $customerId = Arr::get($orderPayload, 'customer_id');
        $email = Arr::get($orderPayload, 'billing.email');

        if (empty($customerId) && empty($email)) {
            return null;
        }

        $attributes = [
            'woocommerce_id' => $customerId ?: null,
        ];

        $values = [
            'email' => $email,
            'first_name' => Arr::get($orderPayload, 'billing.first_name'),
            'last_name' => Arr::get($orderPayload, 'billing.last_name'),
            'company' => Arr::get($orderPayload, 'billing.company'),
            'phone' => Arr::get($orderPayload, 'billing.phone'),
            'meta' => [
                'shipping' => Arr::get($orderPayload, 'shipping'),
            ],
        ];

        if ($customerId) {
            return Customer::updateOrCreate($attributes, $values);
        }

        return Customer::updateOrCreate([
            'email' => $email,
        ], array_merge($values, $attributes));
    }

    protected function syncAddresses(Order $order, array $orderPayload): void
    {
        $this->upsertAddress($order, 'billing', Arr::get($orderPayload, 'billing', []));
        $this->upsertAddress($order, 'shipping', Arr::get($orderPayload, 'shipping', []));
    }

    protected function upsertAddress(Order $order, string $type, array $addressData): void
    {
        if (empty($addressData)) {
            $order->addresses()->where('type', $type)->delete();

            return;
        }

        OrderAddress::updateOrCreate(
            [
                'wc_order_id' => $order->id,
                'type' => $type,
            ],
            [
                'first_name' => Arr::get($addressData, 'first_name'),
                'last_name' => Arr::get($addressData, 'last_name'),
                'company' => Arr::get($addressData, 'company'),
                'address_1' => Arr::get($addressData, 'address_1'),
                'address_2' => Arr::get($addressData, 'address_2'),
                'city' => Arr::get($addressData, 'city'),
                'state' => Arr::get($addressData, 'state'),
                'postcode' => Arr::get($addressData, 'postcode'),
                'country' => Arr::get($addressData, 'country'),
                'email' => Arr::get($addressData, 'email'),
                'phone' => Arr::get($addressData, 'phone'),
            ]
        );
    }

    protected function syncItems(Order $order, array $orderPayload): void
    {
        $lineItems = Arr::get($orderPayload, 'line_items', []);
        $seenItemIds = [];

        foreach ($lineItems as $itemData) {
            $item = OrderItem::updateOrCreate(
                [
                    'wc_order_id' => $order->id,
                    'woocommerce_item_id' => Arr::get($itemData, 'id'),
                ],
                [
                    'product_id' => Arr::get($itemData, 'product_id'),
                    'variation_id' => Arr::get($itemData, 'variation_id'),
                    'name' => Arr::get($itemData, 'name'),
                    'sku' => Arr::get($itemData, 'sku'),
                    'quantity' => (int) Arr::get($itemData, 'quantity', 0),
                    'price' => Arr::get($itemData, 'price', 0),
                    'subtotal' => Arr::get($itemData, 'subtotal', 0),
                    'subtotal_tax' => Arr::get($itemData, 'subtotal_tax', 0),
                    'total' => Arr::get($itemData, 'total', 0),
                    'total_tax' => Arr::get($itemData, 'total_tax', 0),
                    'taxes' => Arr::get($itemData, 'taxes'),
                    'meta' => Arr::get($itemData, 'meta_data'),
                ]
            );

            $seenItemIds[] = $item->id;
        }

        if (! empty($seenItemIds)) {
            $order->items()->whereNotIn('id', $seenItemIds)->delete();
        } else {
            $order->items()->delete();
        }
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value);
    }
}
