<?php

namespace App\Services\WooCommerce;

use App\Models\MiscareStoc;
use App\Models\Produs;
use App\Models\WooCommerce\Order;
use App\Models\WooCommerce\OrderItem;
use Illuminate\Support\Arr;

class OrderFulfillmentService
{
    public function fulfill(Order $order): void
    {
        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return;
        }

        $orderNumber = $this->resolveOrderNumber($order);

        foreach ($order->items as $item) {
            $this->syncItem($item, $orderNumber);
        }
    }

    protected function syncItem(OrderItem $item, string $orderNumber): void
    {
        $sku = trim((string) $item->sku);
        $quantity = (int) $item->quantity;

        if ($sku === '' || $quantity <= 0) {
            return;
        }

        $produs = Produs::query()
            ->where('sku', $sku)
            ->lockForUpdate()
            ->first();

        if (! $produs) {
            return;
        }

        $targetDelta = -$quantity;

        $movement = MiscareStoc::query()
            ->where('wc_order_item_id', $item->id)
            ->lockForUpdate()
            ->first();

        if (! $movement) {
            MiscareStoc::create([
                'wc_order_item_id' => $item->id,
                'produs_id' => $produs->id,
                'user_id' => null,
                'delta' => $targetDelta,
                'nr_comanda' => $orderNumber,
            ]);

            $this->applyQuantityDifference($produs, $targetDelta);

            return;
        }

        $difference = $targetDelta - (int) $movement->delta;

        $updates = [];
        if ($movement->produs_id !== $produs->id) {
            $updates['produs_id'] = $produs->id;
        }

        if ($movement->nr_comanda !== $orderNumber) {
            $updates['nr_comanda'] = $orderNumber;
        }

        if ($difference !== 0) {
            $updates['delta'] = $targetDelta;
        }

        if (! empty($updates)) {
            $movement->fill($updates);
            $movement->save();
        }

        if ($difference !== 0) {
            $this->applyQuantityDifference($produs, $difference);
        }
    }

    protected function applyQuantityDifference(Produs $produs, int $deltaChange): void
    {
        $currentQty = (int) ($produs->cantitate ?? 0);
        $newQty = max($currentQty + $deltaChange, 0);

        $produs->forceFill(['cantitate' => $newQty])->save();
    }

    protected function resolveOrderNumber(Order $order): string
    {
        $number = Arr::get($order->meta ?? [], 'number');

        return $number !== null && $number !== ''
            ? (string) $number
            : (string) $order->woocommerce_id;
    }
}
