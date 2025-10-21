<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Carbon\Carbon;
use App\Models\Produs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class AcasaController extends Controller
{
    public function acasa()
    {
        $lowStock = Produs::whereColumn('cantitate', '<=', 'prag_minim')
            ->orderBy('cantitate')
            ->limit(15)
            ->get();

        $orderMetrics = $this->getOrderMetrics();
        $inventoryMetrics = $this->getInventoryMetrics();
        $procurementMetrics = $this->getProcurementMetrics();
        $moduleLinks = $this->getModuleLinks();

        return view('acasa', compact(
            'lowStock',
            'orderMetrics',
            'inventoryMetrics',
            'procurementMetrics',
            'moduleLinks'
        ));
    }

    protected function getOrderMetrics(): array
    {
        if (! Schema::hasTable('wc_orders')) {
            return [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'blocked' => 0,
                'total_sales_value' => 0.0,
                'sales_last_30_days' => 0.0,
                'oldest_open_order' => null,
            ];
        }

        $blockedStatuses = ['on-hold', 'backordered'];
        $openStatuses = ['pending', 'processing', 'on-hold'];

        $aggregates = DB::table('wc_orders')
            ->selectRaw('
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS processing_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status IN (?, ?) THEN total ELSE 0 END) AS total_sales,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS blocked_count
            ', [
                'pending',
                'processing',
                'completed',
                'completed', 'processing',
                ...$blockedStatuses,
            ])
            ->first();

        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $salesLast30Days = DB::table('wc_orders')
            ->whereIn('status', ['completed', 'processing'])
            ->where(function ($query) use ($thirtyDaysAgo) {
                $query->whereDate('date_created', '>=', $thirtyDaysAgo)
                    ->orWhereDate('created_at', '>=', $thirtyDaysAgo);
            })
            ->sum('total');

        $oldestOpenOrder = DB::table('wc_orders')
            ->whereIn('status', $openStatuses)
            ->orderByRaw('COALESCE(date_created, created_at) asc')
            ->value(DB::raw('COALESCE(date_created, created_at)'));

        return [
            'pending' => (int) ($aggregates->pending_count ?? 0),
            'processing' => (int) ($aggregates->processing_count ?? 0),
            'completed' => (int) ($aggregates->completed_count ?? 0),
            'blocked' => (int) ($aggregates->blocked_count ?? 0),
            'total_sales_value' => (float) ($aggregates->total_sales ?? 0),
            'sales_last_30_days' => (float) $salesLast30Days,
            'oldest_open_order' => $oldestOpenOrder ? Carbon::parse($oldestOpenOrder) : null,
        ];
    }

    protected function getInventoryMetrics(): array
    {
        $metrics = [
            'on_hand_units' => 0,
            'current_value' => 0.0,
            'low_stock_count' => Produs::whereColumn('cantitate', '<=', 'prag_minim')->count(),
            'movements_last_7_days' => [
                'inbound_units' => 0,
                'outbound_units' => 0,
            ],
            'recent_movements' => collect(),
        ];

        if (Schema::hasTable('produse')) {
            $metrics['on_hand_units'] = (int) (DB::table('produse')->sum('cantitate') ?? 0);
            $metrics['current_value'] = (float) (DB::table('produse')
                ->selectRaw('SUM(cantitate * pret) as total_value')
                ->value('total_value') ?? 0);
        }

        if (Schema::hasTable('miscari_stoc')) {
            $windowStart = Carbon::now()->subDays(7);

            $movement = DB::table('miscari_stoc')
                ->selectRaw('
                    SUM(CASE WHEN delta > 0 THEN delta ELSE 0 END) AS inbound_units,
                    SUM(CASE WHEN delta < 0 THEN ABS(delta) ELSE 0 END) AS outbound_units
                ')
                ->when(Schema::hasColumn('miscari_stoc', 'created_at'), function ($query) use ($windowStart) {
                    $query->where('created_at', '>=', $windowStart);
                })
                ->first();

            $metrics['movements_last_7_days'] = [
                'inbound_units' => (int) ($movement->inbound_units ?? 0),
                'outbound_units' => (int) ($movement->outbound_units ?? 0),
            ];

            $movementColumns = ['ms.nr_comanda', 'ms.delta', 'p.nume as produs'];
            if (Schema::hasColumn('miscari_stoc', 'created_at')) {
                $movementColumns[] = 'ms.created_at';
            }

            $recentMovementsQuery = DB::table('miscari_stoc as ms')
                ->leftJoin('produse as p', 'p.id', '=', 'ms.produs_id')
                ->select($movementColumns)
                ->limit(5);

            if (Schema::hasColumn('miscari_stoc', 'created_at')) {
                $recentMovementsQuery->orderByDesc('ms.created_at');
            }

            $metrics['recent_movements'] = $recentMovementsQuery->get()->map(function ($movement) {
                if (! empty($movement->created_at)) {
                    $movement->created_at = Carbon::parse($movement->created_at);
                }

                return $movement;
            });
        }

        return $metrics;
    }

    protected function getProcurementMetrics(): array
    {
        $metrics = [
            'outstanding_count' => 0,
            'outstanding_value' => 0.0,
            'overdue_count' => 0,
            'next_eta' => null,
        ];

        if (! Schema::hasTable('procurement_purchase_orders')) {
            return $metrics;
        }

        $openStatuses = ['draft', 'pending', 'approved', 'sent', 'partial'];
        $poQuery = DB::table('procurement_purchase_orders');

        if (Schema::hasColumn('procurement_purchase_orders', 'status')) {
            $poQuery->whereIn('status', $openStatuses);
        } elseif (Schema::hasColumn('procurement_purchase_orders', 'received_at')) {
            $poQuery->whereNull('received_at');
        }

        $metrics['outstanding_count'] = (clone $poQuery)->count();

        $valueColumn = null;
        foreach (['total_value', 'total', 'grand_total'] as $candidate) {
            if (Schema::hasColumn('procurement_purchase_orders', $candidate)) {
                $valueColumn = $candidate;
                break;
            }
        }

        if ($valueColumn) {
            $metrics['outstanding_value'] = (float) ((clone $poQuery)->sum($valueColumn) ?? 0);
        }

        $expectedColumn = null;
        foreach (['expected_at', 'expected_date', 'eta', 'due_date'] as $candidate) {
            if (Schema::hasColumn('procurement_purchase_orders', $candidate)) {
                $expectedColumn = $candidate;
                break;
            }
        }

        if ($expectedColumn) {
            $metrics['overdue_count'] = (clone $poQuery)
                ->where($expectedColumn, '<', Carbon::now())
                ->count();

            $metrics['next_eta'] = (clone $poQuery)
                ->where($expectedColumn, '>=', Carbon::now()->startOfDay())
                ->orderBy($expectedColumn)
                ->value($expectedColumn);
        }

        if (Schema::hasTable('procurement_purchase_order_items')) {
            $quantityColumn = $this->resolveColumn('procurement_purchase_order_items', ['quantity', 'qty', 'ordered_quantity']);
            $priceColumn = $this->resolveColumn('procurement_purchase_order_items', ['unit_price', 'price', 'cost']);

            if ($quantityColumn && $priceColumn) {
                $itemQuery = DB::table('procurement_purchase_order_items as poi')
                    ->join('procurement_purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id');

                if (Schema::hasColumn('procurement_purchase_orders', 'status')) {
                    $itemQuery->whereIn('po.status', $openStatuses);
                } elseif (Schema::hasColumn('procurement_purchase_orders', 'received_at')) {
                    $itemQuery->whereNull('po.received_at');
                }

                $incomingValue = $itemQuery
                    ->selectRaw("SUM({$quantityColumn} * {$priceColumn}) as value")
                    ->value('value');

                if (! is_null($incomingValue)) {
                    $metrics['outstanding_value'] = max(
                        $metrics['outstanding_value'],
                        (float) $incomingValue
                    );
                }
            }
        }

        if (! empty($metrics['next_eta'])) {
            $metrics['next_eta'] = Carbon::parse($metrics['next_eta']);
        }

        return $metrics;
    }

    protected function getModuleLinks(): array
    {
        return [
            'orders' => $this->routeIfExists('woocommerce.orders.index'),
            'orders_pending' => $this->routeIfExists('woocommerce.orders.index', ['status' => 'pending']),
            'orders_blocked' => $this->routeIfExists('woocommerce.orders.index', ['status' => 'on-hold']),
            'orders_completed' => $this->routeIfExists('woocommerce.orders.index', ['status' => 'completed']),
            'inventory' => $this->routeIfExists('produse.index'),
            'movements' => $this->routeIfExists('miscari.intrari'),
            'procurement' => $this->routeIfExists('procurement.purchase-orders.index', [], url('/procurement/purchase-orders')),
        ];
    }

    protected function routeIfExists(string $name, array $parameters = [], ?string $fallback = null): ?string
    {
        if (Route::has($name)) {
            return route($name, $parameters);
        }

        return $fallback;
    }

    protected function resolveColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }
}
