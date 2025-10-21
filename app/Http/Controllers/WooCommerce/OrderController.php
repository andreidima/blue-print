<?php

namespace App\Http\Controllers\WooCommerce;

use App\Http\Controllers\Controller;
use App\Models\WooCommerce\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        $ordersQuery = Order::query()
            ->with([
                'customer',
                'addresses' => fn ($q) => $q->where('type', 'billing'),
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
            })
            ->orderByDesc('date_created')
            ->orderByDesc('id');

        $orders = $ordersQuery->paginate(25)->withQueryString();

        $statusOptions = Order::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        return view('woocommerce.orders.index', [
            'orders' => $orders,
            'searchTerm' => $searchTerm,
            'status' => $status,
            'searchIntervalData' => $searchIntervalData,
            'statusOptions' => $statusOptions,
            'formAction' => route($routeName),
        ]);
    }
}
