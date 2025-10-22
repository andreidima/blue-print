<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\ReceivePurchaseOrderRequest;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Requests\Procurement\UpdatePurchaseOrderRequest;
use App\Models\MiscareStoc;
use App\Models\Produs;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use App\Models\Procurement\Supplier;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    protected array $productNameCache = [];

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $query = PurchaseOrder::query()
            ->with('supplier')
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        /** @var LengthAwarePaginator $purchaseOrders */
        $purchaseOrders = $query->paginate(20)->withQueryString();

        return view('procurement.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'statuses' => PurchaseOrder::STATUSES,
            'activeStatus' => $status,
        ]);
    }

    public function create(): View
    {
        return view('procurement.purchase-orders.create', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Produs::orderBy('nume')->get(),
            'statuses' => PurchaseOrder::STATUSES,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = DB::transaction(function () use ($validated) {
            $items = $this->prepareItems($validated['items']);
            $supplier = $this->resolveSupplier($validated);

            $purchaseOrder = new PurchaseOrder();
            if ($supplier) {
                $purchaseOrder->supplier()->associate($supplier);
            }
            $purchaseOrder->po_number = $validated['po_number'];
            $purchaseOrder->status = $validated['status'];
            $purchaseOrder->expected_at = $validated['expected_at'] ?? null;
            $purchaseOrder->notes = $validated['notes'] ?? null;
            $purchaseOrder->total_value = $items->sum('line_total');
            $purchaseOrder->save();

            $this->syncItems($purchaseOrder, $items);

            return $purchaseOrder;
        });

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('status', 'Comanda de achiziție a fost creată cu succes.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'items.produs']);

        return view('procurement.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'items']);

        return view('procurement.purchase-orders.edit', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Produs::orderBy('nume')->get(),
            'statuses' => PurchaseOrder::STATUSES,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $purchaseOrder) {
            $existingReceived = $purchaseOrder->items->mapWithKeys(function (PurchaseOrderItem $item) {
                return [$this->itemKey($item->produs_id, $item->description) => $item->received_quantity];
            })->all();

            $items = $this->prepareItems($validated['items'], $existingReceived);
            $supplier = $this->resolveSupplier($validated, $purchaseOrder->supplier);

            if ($supplier) {
                $purchaseOrder->supplier()->associate($supplier);
            } else {
                $purchaseOrder->supplier()->dissociate();
            }

            $purchaseOrder->po_number = $validated['po_number'];
            $purchaseOrder->status = $validated['status'];
            $purchaseOrder->expected_at = $validated['expected_at'] ?? null;
            $purchaseOrder->notes = $validated['notes'] ?? null;
            $purchaseOrder->total_value = $items->sum('line_total');
            $purchaseOrder->save();

            $purchaseOrder->items()->delete();
            $this->syncItems($purchaseOrder, $items);
        });

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('status', 'Comanda de achiziție a fost actualizată.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->delete();

        return redirect()
            ->route('procurement.purchase-orders.index')
            ->with('status', 'Comanda de achiziție a fost ștearsă.');
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status === PurchaseOrder::STATUS_RECEIVED) {
            return redirect()
                ->route('procurement.purchase-orders.show', $purchaseOrder)
                ->with('status', 'Această comandă a fost deja marcată ca recepționată.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $purchaseOrder) {
            $receivedAt = ! empty($validated['received_at'])
                ? Carbon::parse($validated['received_at'])
                : Carbon::now();

            $itemsInput = $validated['items'] ?? [];
            $createMovements = (bool) ($validated['create_movements'] ?? false);

            $purchaseOrder->load('items.produs');

            foreach ($purchaseOrder->items as $item) {
                $requestedQuantity = $itemsInput[$item->id]['received_quantity'] ?? $item->quantity;
                $requestedQuantity = (float) $requestedQuantity;
                $requestedQuantity = min($requestedQuantity, (float) $item->quantity);
                $requestedQuantity = max($requestedQuantity, (float) $item->received_quantity);

                $delta = $requestedQuantity - (float) $item->received_quantity;

                if ($createMovements && $delta > 0 && $item->produs) {
                    $this->applyStockMovement($item, $delta, $purchaseOrder->po_number);
                }

                $item->received_quantity = $requestedQuantity;
                $item->save();
            }

            if (! empty($validated['notes'])) {
                $notes = trim((string) $purchaseOrder->notes);
                $notes = $notes
                    ? $notes . "\n\nNotă recepție (" . Carbon::now()->format('d.m.Y H:i') . "): " . $validated['notes']
                    : $validated['notes'];
                $purchaseOrder->notes = $notes;
            }

            $purchaseOrder->markAsReceived($receivedAt);
            $purchaseOrder->received_by = Auth::id();
            $purchaseOrder->save();
        });

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('status', 'Comanda a fost marcată ca recepționată.');
    }

    protected function resolveSupplier(array $validated, ?Supplier $current = null): ?Supplier
    {
        $supplierId = $validated['supplier_id'] ?? null;
        $supplierData = $validated['supplier'] ?? null;

        if ($supplierId) {
            $supplier = Supplier::find($supplierId);
            if ($supplier && $supplierData) {
                $this->updateSupplier($supplier, $supplierData);
            }

            return $supplier;
        }

        if (! $supplierData) {
            return $current;
        }

        $supplier = $current ?: new Supplier();

        $this->updateSupplier($supplier, $supplierData, $current === null);

        return $supplier;
    }

    protected function updateSupplier(Supplier $supplier, array $data, bool $forceName = false): void
    {
        $fields = ['contact_name', 'email', 'phone', 'reference', 'notes'];

        if ($forceName || array_key_exists('name', $data)) {
            $supplier->name = $data['name'] ?? $supplier->name;
        }

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $supplier->{$field} = $data[$field];
            }
        }

        $supplier->save();
    }

    protected function prepareItems(array $items, array $existingReceived = []): Collection
    {
        return collect($items)
            ->map(function (array $item) use ($existingReceived) {
                $produsId = $item['produs_id'] ?? null;
                $description = $item['description'] ?? null;
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $lineTotal = round($quantity * $unitPrice, 2);

                if (! $description) {
                    $description = $this->resolveProductName($produsId);
                }

                $key = $this->itemKey($produsId, $description);
                $received = $existingReceived[$key] ?? 0;
                $receivedQuantity = min((float) $received, $quantity);

                return [
                    'produs_id' => $produsId,
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'received_quantity' => $receivedQuantity,
                    'notes' => $item['notes'] ?? null,
                ];
            });
    }

    protected function syncItems(PurchaseOrder $purchaseOrder, Collection $items): void
    {
        foreach ($items as $itemData) {
            $item = new PurchaseOrderItem();
            $item->purchase_order_id = $purchaseOrder->id;
            $item->produs_id = $itemData['produs_id'];
            $item->description = $itemData['description'];
            $item->quantity = $itemData['quantity'];
            $item->unit_price = $itemData['unit_price'];
            $item->line_total = $itemData['line_total'];
            $item->received_quantity = $itemData['received_quantity'];
            $item->notes = $itemData['notes'];
            $item->save();
        }
    }

    protected function resolveProductName(?int $productId): ?string
    {
        if (! $productId) {
            return null;
        }

        if (! array_key_exists($productId, $this->productNameCache)) {
            $this->productNameCache[$productId] = Produs::find($productId)?->nume;
        }

        return $this->productNameCache[$productId];
    }

    protected function itemKey(?int $productId, ?string $description): string
    {
        $description = $description ? mb_strtolower($description) : '';

        return ($productId ?? 'null') . '|' . $description;
    }

    protected function applyStockMovement(PurchaseOrderItem $item, float $delta, string $poNumber): void
    {
        $product = $item->produs;
        if (! $product) {
            return;
        }

        MiscareStoc::create([
            'produs_id' => $product->id,
            'user_id' => Auth::id(),
            'delta' => $delta,
            'nr_comanda' => $poNumber,
        ]);

        $product->cantitate = $product->cantitate + $delta;
        $product->save();
    }
}
