@php
    $purchaseOrder = $purchaseOrder ?? null;
    $itemsInput = old('items');
    if (! is_array($itemsInput) || empty($itemsInput)) {
        $itemsInput = $purchaseOrder
            ? $purchaseOrder->items->map(fn ($item) => [
                'produs_id' => $item->produs_id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'notes' => $item->notes,
            ])->toArray()
            : [[]];
    }
    $nextIndex = count($itemsInput);
@endphp

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Detalii furnizor</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="supplier_id">Alege furnizor existent</label>
                <select class="form-select" id="supplier_id" name="supplier_id">
                    <option value="">— Selectează furnizor —</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id', $purchaseOrder?->supplier?->id) == $supplier->id)>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="supplier_name">Nume furnizor nou</label>
                <input type="text" class="form-control" id="supplier_name" name="supplier[name]" value="{{ old('supplier.name', $purchaseOrder?->supplier?->name) }}" placeholder="Introdu numele furnizorului">
                <small class="text-muted">Completează pentru a crea sau actualiza detaliile furnizorului.</small>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="supplier_contact">Persoană de contact</label>
                <input type="text" class="form-control" id="supplier_contact" name="supplier[contact_name]" value="{{ old('supplier.contact_name', $purchaseOrder?->supplier?->contact_name) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="supplier_email">Email</label>
                <input type="email" class="form-control" id="supplier_email" name="supplier[email]" value="{{ old('supplier.email', $purchaseOrder?->supplier?->email) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="supplier_phone">Telefon</label>
                <input type="text" class="form-control" id="supplier_phone" name="supplier[phone]" value="{{ old('supplier.phone', $purchaseOrder?->supplier?->phone) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="supplier_reference">Referință internă</label>
                <input type="text" class="form-control" id="supplier_reference" name="supplier[reference]" value="{{ old('supplier.reference', $purchaseOrder?->supplier?->reference) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="supplier_notes">Note furnizor</label>
                <textarea class="form-control" id="supplier_notes" name="supplier[notes]" rows="2">{{ old('supplier.notes', $purchaseOrder?->supplier?->notes) }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Detalii comandă</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="po_number">Număr PO</label>
                <input type="text" class="form-control" id="po_number" name="po_number" value="{{ old('po_number', $purchaseOrder?->po_number) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status" required>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $purchaseOrder?->status ?? 'draft') === $status)>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="expected_at">Data estimată recepție</label>
                <input type="date" class="form-control" id="expected_at" name="expected_at" value="{{ old('expected_at', optional($purchaseOrder?->expected_at)->format('Y-m-d')) }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Note comandă</label>
                <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $purchaseOrder?->notes) }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h5 mb-0">Articole comandă</h2>
            <button class="btn btn-outline-primary btn-sm" type="button" id="add-po-item">
                <i class="fa-solid fa-plus me-1"></i> Adaugă linie
            </button>
        </div>

        <div id="po-items" data-next-index="{{ $nextIndex }}">
            @foreach($itemsInput as $index => $item)
                <div class="border rounded-3 p-3 mb-3 po-item-row" data-index="{{ $index }}">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <strong>Linia #{{ $index + 1 }}</strong>
                        </div>
                        <button class="btn btn-sm btn-outline-danger remove-po-item" type="button" title="Șterge linia">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Produs existent</label>
                            <select class="form-select" name="items[{{ $index }}][produs_id]">
                                <option value="">— fără asociere —</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" @selected((string)($item['produs_id'] ?? '') === (string) $product->id)>
                                        {{ $product->nume }} @if($product->sku) (SKU: {{ $product->sku }}) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Descriere</label>
                            <input type="text" class="form-control" name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" placeholder="Ex: Plăci MDF 18mm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cantitate</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? '' }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Preț unitar</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Note linie</label>
                            <textarea class="form-control" name="items[{{ $index }}][notes]" rows="2">{{ $item['notes'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center">
    <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-outline-secondary">Anulează</a>
    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-save me-1"></i> Salvează
    </button>
</div>

<template id="po-item-template">
    <div class="border rounded-3 p-3 mb-3 po-item-row">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <strong></strong>
            </div>
            <button class="btn btn-sm btn-outline-danger remove-po-item" type="button" title="Șterge linia">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Produs existent</label>
                <select class="form-select" data-name="produs_id">
                    <option value="">— fără asociere —</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">
                            {{ $product->nume }} @if($product->sku) (SKU: {{ $product->sku }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Descriere</label>
                <input type="text" class="form-control" data-name="description" placeholder="Ex: Plăci MDF 18mm">
            </div>
            <div class="col-md-2">
                <label class="form-label">Cantitate</label>
                <input type="number" step="0.01" min="0" class="form-control" data-name="quantity">
            </div>
            <div class="col-md-2">
                <label class="form-label">Preț unitar</label>
                <input type="number" step="0.01" min="0" class="form-control" data-name="unit_price">
            </div>
            <div class="col-12">
                <label class="form-label">Note linie</label>
                <textarea class="form-control" rows="2" data-name="notes"></textarea>
            </div>
        </div>
    </div>
</template>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const itemsContainer = document.getElementById('po-items');
            const template = document.getElementById('po-item-template');
            const addButton = document.getElementById('add-po-item');

            if (!itemsContainer || !template || !addButton) {
                return;
            }

            const updateRowHeaders = () => {
                itemsContainer.querySelectorAll('.po-item-row').forEach((row, index) => {
                    const header = row.querySelector('strong');
                    if (header) {
                        header.textContent = `Linia #${index + 1}`;
                    }
                    row.setAttribute('data-index', index);
                    row.querySelectorAll('select[data-name], input[data-name], textarea[data-name]').forEach((element) => {
                        const field = element.getAttribute('data-name');
                        element.setAttribute('name', `items[${index}][${field}]`);
                        element.removeAttribute('data-name');
                    });
                    row.querySelectorAll('select[name^="items["]').forEach((select) => {
                        if (!select.hasAttribute('data-initialized')) {
                            select.setAttribute('data-initialized', 'true');
                        }
                    });
                });
            };

            updateRowHeaders();

            addButton.addEventListener('click', (event) => {
                event.preventDefault();
                const clone = template.content.cloneNode(true);
                itemsContainer.appendChild(clone);
                updateRowHeaders();
            });

            itemsContainer.addEventListener('click', (event) => {
                const target = event.target.closest('.remove-po-item');
                if (!target) {
                    return;
                }

                event.preventDefault();
                const row = target.closest('.po-item-row');
                if (row) {
                    row.remove();
                    if (itemsContainer.children.length === 0) {
                        const clone = template.content.cloneNode(true);
                        itemsContainer.appendChild(clone);
                    }
                    updateRowHeaders();
                }
            });
        });
    </script>
@endonce
