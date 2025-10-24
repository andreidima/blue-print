<?php

namespace App\Http\Controllers\WooCommerce;

use App\Http\Controllers\Controller;
use App\Models\WooCommerce\Order;
use App\Models\WooCommerce\SyncState;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\RedirectResponse;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderOrders($request, 'woocommerce.orders.index');
    }

    public function publicIndex(Request $request)
    {
        return $this->renderOrders($request, 'woocommerce.orders.preview');
    }

    protected function renderOrders(Request $request, string $routeName)
    {
        $searchTerm = $request->query('searchTerm');
        $status = $request->query('status');
        $searchIntervalData = $request->query('searchIntervalData');

        $sort = $request->query('sort');
        $direction = strtolower($request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $ordersQuery = Order::query()
            ->select('wc_orders.*')
            ->with([
                'customer',
                'addresses' => fn ($q) => $q->where('type', 'billing'),
                'items' => fn ($q) => $q
                    ->select('id', 'wc_order_id', 'name', 'sku', 'quantity')
                    ->with(['stockMovements' => fn ($movements) => $movements
                        ->select('id', 'wc_order_item_id', 'delta')
                        ->where('delta', '<', 0)
                    ]),
            ])
            ->withCount('items')
            ->when($searchTerm, function ($query) use ($searchTerm) {
                $query->where(function ($query) use ($searchTerm) {
                    $query->where('woocommerce_id', 'like', "%{$searchTerm}%")
                        ->orWhere('meta->number', 'like', "%{$searchTerm}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                            $customerQuery->where('first_name', 'like', "%{$searchTerm}%")
                                ->orWhere('last_name', 'like', "%{$searchTerm}%")
                                ->orWhere('email', 'like', "%{$searchTerm}%");
                        })
                        ->orWhereHas('addresses', function ($addressQuery) use ($searchTerm) {
                            $addressQuery->where('type', 'billing')
                                ->where(function ($addressQuery) use ($searchTerm) {
                                    $addressQuery->where('first_name', 'like', "%{$searchTerm}%")
                                        ->orWhere('last_name', 'like', "%{$searchTerm}%")
                                        ->orWhere('email', 'like', "%{$searchTerm}%")
                                        ->orWhere('phone', 'like', "%{$searchTerm}%");
                                });
                        });
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($searchIntervalData, function ($query, $searchIntervalData) {
                $dates = array_pad(explode(',', $searchIntervalData), 2, null);
                [$start, $end] = $dates;

                if ($start || $end) {
                    $startDate = $start ? Carbon::parse($start)->startOfDay() : null;
                    $endDate = $end ? Carbon::parse($end)->endOfDay() : null;

                    if ($startDate && $endDate) {
                        $query->whereBetween('date_created', [$startDate, $endDate]);
                    } elseif ($startDate) {
                        $query->where('date_created', '>=', $startDate);
                    } elseif ($endDate) {
                        $query->where('date_created', '<=', $endDate);
                    }
                }
            });

        if ($sort === 'number') {
            $ordersQuery
                ->orderByRaw(
                    "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.number')), ''), CAST(woocommerce_id AS CHAR)) {$direction}"
                )
                ->orderBy('wc_orders.id', $direction);
        } elseif ($sort === 'name') {
            $ordersQuery
                ->leftJoin('wc_customers', 'wc_orders.wc_customer_id', '=', 'wc_customers.id')
                ->leftJoin('wc_order_addresses as billing_addresses', function ($join) {
                    $join->on('wc_orders.id', '=', 'billing_addresses.wc_order_id')
                        ->where('billing_addresses.type', '=', 'billing');
                })
                ->orderByRaw(
                    "LOWER(COALESCE(NULLIF(CONCAT_WS(' ', NULLIF(wc_customers.first_name, ''), NULLIF(wc_customers.last_name, '')), ''), NULLIF(CONCAT_WS(' ', NULLIF(billing_addresses.first_name, ''), NULLIF(billing_addresses.last_name, '')), ''), '')) {$direction}"
                )
                ->orderBy('wc_orders.id', $direction);
        } else {
            $ordersQuery
                ->orderByDesc('wc_orders.date_created')
                ->orderByDesc('wc_orders.id');
        }

        $orders = $ordersQuery->paginate(25)->withQueryString();

        $orders->getCollection()->transform(function (Order $order) {
            $totalQuantity = $order->items->sum(fn ($item) => (int) ($item->quantity ?? 0));
            $fulfilledQuantity = $order->items->sum(fn ($item) => $item->stockMovements->sum(fn ($movement) => abs((int) $movement->delta)));

            $order->setAttribute('fulfillment_total_quantity', $totalQuantity);
            $order->setAttribute('fulfillment_fulfilled_quantity', $fulfilledQuantity);
            $order->setAttribute('fulfillment', $this->summarizeFulfillment($fulfilledQuantity, $totalQuantity));

            return $order;
        });

        $statusOptions = Order::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $lastSyncedState = SyncState::query()->where('key', 'orders.last_synced_at')->first();
        $lastSyncedAt = $lastSyncedState ? Carbon::parse($lastSyncedState->value)->setTimezone(config('app.timezone')) : null;

        return view('woocommerce.orders.index', [
            'orders' => $orders,
            'searchTerm' => $searchTerm,
            'status' => $status,
            'searchIntervalData' => $searchIntervalData,
            'statusOptions' => $statusOptions,
            'formAction' => route($routeName),
            'sort' => $sort,
            'direction' => $direction,
            'lastSyncedAt' => $lastSyncedAt,
        ]);
    }

    protected function summarizeFulfillment(int $fulfilled, int $ordered): array
    {
        if ($ordered <= 0) {
            return [
                'status' => null,
                'label' => 'Fără legătură',
                'badge' => 'bg-secondary',
            ];
        }

        if ($fulfilled >= $ordered) {
            return [
                'status' => 'fulfilled',
                'label' => 'Finalizat',
                'badge' => 'bg-success',
            ];
        }

        if ($fulfilled > 0) {
            return [
                'status' => 'partial',
                'label' => 'Parțial',
                'badge' => 'bg-warning text-dark',
            ];
        }

        return [
            'status' => 'pending',
            'label' => 'În așteptare',
            'badge' => 'bg-secondary',
        ];
    }

    public function sync(Request $request): RedirectResponse
    {
        $missingConfiguration = collect([
            'url' => config('woocommerce.url'),
            'consumer_key' => config('woocommerce.consumer_key'),
            'consumer_secret' => config('woocommerce.consumer_secret'),
        ])->filter(fn ($value) => empty($value));

        if ($missingConfiguration->isNotEmpty()) {
            return back()->with('error', 'Sincronizarea WooCommerce nu poate fi inițiată deoarece lipsesc datele de configurare.');
        }

        $previousSyncValue = SyncState::query()->where('key', 'orders.last_synced_at')->value('value');

        try {
            $exitCode = Artisan::call('woocommerce:sync-orders');
            $output = trim(Artisan::output());
        } catch (Throwable $exception) {
            $message = 'Sincronizarea WooCommerce a eșuat: ' . e($exception->getMessage());

            return back()->with('error', $message);
        }

        if ($exitCode === 0) {
            $latestSyncValue = SyncState::query()->where('key', 'orders.last_synced_at')->value('value');

            if ($latestSyncValue === $previousSyncValue) {
                SyncState::updateOrCreate(
                    ['key' => 'orders.last_synced_at'],
                    ['value' => Carbon::now()->utc()->toIso8601String()]
                );
            }

            $message = 'Sincronizarea WooCommerce a fost inițiată cu succes.';

            if ($output !== '') {
                $message .= sprintf(
                    '<pre class="mb-0 mt-2 small bg-light border rounded p-2">%s</pre>',
                    e($output)
                );
            }

            return back()->with('success', $message);
        }

        $message = sprintf('Sincronizarea WooCommerce a eșuat (cod ieșire: %d).', $exitCode);

        if ($output !== '') {
            $message .= sprintf(
                '<pre class="mb-0 mt-2 small bg-light border rounded p-2">%s</pre>',
                e($output)
            );
        }

        return back()->with('error', $message);
    }
}
