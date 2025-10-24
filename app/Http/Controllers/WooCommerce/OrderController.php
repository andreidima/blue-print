<?php

namespace App\Http\Controllers\WooCommerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\WooCommerce\OrderStatusRequest;
use App\Models\WooCommerce\Order;
use App\Models\WooCommerce\SyncState;
use App\Services\WooCommerce\Exceptions\WooCommerceRequestException;
use App\Services\WooCommerce\OrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
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

    public function updateStatus(
        OrderStatusRequest $request,
        Order $order,
        OrderStatusService $orderStatusService
    ): RedirectResponse {
        $this->authorize('admin-action');

        $status = $request->validated()['status'];

        try {
            $orderStatusService->updateStatus($order, $status);

            return back()->with('status', 'Statusul comenzii a fost actualizat cu succes.');
        } catch (WooCommerceRequestException $exception) {
            report($exception);

            $message = 'Actualizarea statusului comenzii în WooCommerce a eșuat.';
            $response = $exception->response();

            if ($response) {
                $details = $response->json('message') ?? $response->body();

                if (is_array($details)) {
                    $details = collect($details)
                        ->flatten()
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->implode(' ');
                } else {
                    $details = trim((string) $details);
                }

                if ($details !== '') {
                    $message .= ' Detalii: ' . $details;
                }
            }

            return back()
                ->withInput($request->only('status'))
                ->with('error', $message);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->only('status'))
                ->with('error', 'A apărut o eroare neașteptată la actualizarea statusului comenzii.');
        }
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

            [$summaryText, $detailsHtml] = $this->summarizeSyncSuccessOutput($output);

            $message = 'Sincronizarea WooCommerce s-a încheiat cu succes.';

            if ($summaryText !== null) {
                $message .= ' ' . $summaryText;
            }

            if ($detailsHtml !== null) {
                $message .= $detailsHtml;
            }

            return back()->with('success', $message);
        }

        [$summaryText, $detailsHtml] = $this->summarizeSyncFailureOutput($output, $exitCode);

        $message = 'Sincronizarea WooCommerce nu a reușit.';

        if ($summaryText !== null) {
            $message .= ' ' . $summaryText;
        }

        if ($detailsHtml !== null) {
            $message .= $detailsHtml;
        }

        return back()->with('error', $message);
    }

    private function summarizeSyncSuccessOutput(string $output): array
    {
        $normalized = trim(preg_replace('/\r\n?/', "\n", $output));

        if ($normalized === '') {
            return [null, null];
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $normalized)), fn ($line) => $line !== ''));

        $summaryParts = [];
        $detailsSince = null;

        foreach ($lines as $line) {
            if (preg_match('/Processed\s+(\d+)\s+orders?/i', $line, $matches)) {
                $count = (int) $matches[1];

                if ($count === 0) {
                    $summaryParts[] = 'Nu au fost găsite comenzi noi de sincronizat.';
                } elseif ($count === 1) {
                    $summaryParts[] = 'A fost actualizată 1 comandă.';
                } else {
                    $summaryParts[] = sprintf('Au fost actualizate %d comenzi.', $count);
                }
            }

            if ($detailsSince === null && preg_match('/updated since\s+(.+)$/i', $line, $sinceMatches)) {
                try {
                    $since = Carbon::parse($sinceMatches[1])->setTimezone(config('app.timezone'));
                    $detailsSince = 'Au fost verificate comenzile actualizate după ' . $since->translatedFormat('d.m.Y H:i') . '.';
                } catch (Throwable $exception) {
                    // Ignore parsing issues and keep the raw output in the technical details section.
                }
            }
        }

        if ($detailsSince !== null) {
            array_unshift($summaryParts, $detailsSince);
        }

        $summaryText = empty($summaryParts) ? null : implode(' ', $summaryParts);

        $detailsHtml = sprintf(
            '<details class="mt-2"><summary class="small text-muted">Vezi detaliile tehnice</summary><pre class="mb-0 mt-2 small bg-light border rounded p-2">%s</pre></details>',
            e($normalized)
        );

        return [$summaryText, $detailsHtml];
    }

    private function summarizeSyncFailureOutput(string $output, int $exitCode): array
    {
        $normalized = trim(preg_replace('/\r\n?/', "\n", $output));

        if ($normalized === '') {
            return [
                'A apărut o eroare neașteptată. Încearcă din nou sau contactează un administrator.',
                null,
            ];
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $normalized)), fn ($line) => $line !== ''));

        $summaryParts = [];
        $checkedSince = null;

        foreach ($lines as $line) {
            if (stripos($line, 'Failed to save orders locally') !== false) {
                $summaryParts[] = 'Comenzile nu au putut fi salvate în aplicație. Încearcă din nou sau contactează un administrator.';
                continue;
            }

            if (stripos($line, 'authentication') !== false && stripos($line, 'failed') !== false) {
                $summaryParts[] = 'Autentificarea la WooCommerce a eșuat. Verifică datele de conectare.';
                continue;
            }

            if (preg_match('/Fetching\s+WooCommerce\s+orders\s+updated\s+since\s+(.+)$/i', $line, $matches)) {
                try {
                    $since = Carbon::parse($matches[1])->setTimezone(config('app.timezone'));
                    $checkedSince = 'S-au verificat comenzile actualizate după ' . $since->translatedFormat('d.m.Y H:i') . ', însă procesul s-a oprit înainte să se finalizeze.';
                } catch (Throwable $exception) {
                    // If parsing fails we simply ignore this line for the human summary.
                }

                continue;
            }
        }

        if ($checkedSince !== null) {
            array_unshift($summaryParts, $checkedSince);
        }

        if (empty($summaryParts)) {
            $summaryParts[] = 'A apărut o eroare (cod ' . $exitCode . '). Încearcă din nou sau contactează un administrator.';
        }

        $detailsHtml = sprintf(
            '<details class="mt-2"><summary class="small text-muted">Vezi detaliile tehnice</summary><pre class="mb-0 mt-2 small bg-light border rounded p-2">%s</pre></details>',
            e($normalized)
        );

        return [implode(' ', $summaryParts), $detailsHtml];
    }
}
