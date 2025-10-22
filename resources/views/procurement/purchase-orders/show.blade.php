@extends('layouts.app')

@php
    use App\Models\Procurement\PurchaseOrder;
    use Carbon\Carbon;
@endphp

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Comanda #{{ $purchaseOrder->po_number }}</h1>
            <p class="text-muted mb-0">Vizualizează detaliile și liniile comenzii de achiziție.</p>
        </div>
        <div class="btn-group" role="group">
            <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Înapoi la listă
            </a>
            <a href="{{ route('procurement.purchase-orders.edit', $purchaseOrder) }}" class="btn btn-outline-primary">
                <i class="fa-solid fa-pen me-1"></i> Editează
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Status comandă</h2>
                    <p class="mb-2">
                        <span class="badge bg-{{ $purchaseOrder->status === PurchaseOrder::STATUS_RECEIVED ? 'success' : ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED ? 'secondary' : 'info') }}">
                            {{ ucfirst($purchaseOrder->status) }}
                        </span>
                    </p>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <strong>Data estimată:</strong>
                            @if($purchaseOrder->expected_at)
                                {{ $purchaseOrder->expected_at->format('d.m.Y') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </li>
                        <li class="mb-2">
                            <strong>Valoare totală:</strong>
                            {{ number_format($purchaseOrder->total_value, 2, ',', '.') }} lei
                        </li>
                        <li class="mb-2">
                            <strong>Recepționat la:</strong>
                            @if($purchaseOrder->received_at)
                                {{ $purchaseOrder->received_at->format('d.m.Y H:i') }}
                            @else
                                <span class="text-muted">Încă nu</span>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Furnizor</h2>
                    @if($purchaseOrder->supplier)
                        <p class="mb-1 fw-semibold">{{ $purchaseOrder->supplier->name }}</p>
                        <ul class="list-unstyled small mb-0">
                            <li><strong>Contact:</strong> {{ $purchaseOrder->supplier->contact_name ?? '—' }}</li>
                            <li><strong>Email:</strong> {{ $purchaseOrder->supplier->email ?? '—' }}</li>
                            <li><strong>Telefon:</strong> {{ $purchaseOrder->supplier->phone ?? '—' }}</li>
                            <li><strong>Referință:</strong> {{ $purchaseOrder->supplier->reference ?? '—' }}</li>
                        </ul>
                        @if($purchaseOrder->supplier->notes)
                            <p class="small mt-3 mb-0">{{ $purchaseOrder->supplier->notes }}</p>
                        @endif
                    @else
                        <p class="text-muted mb-0">Nu este setat un furnizor pentru această comandă.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Articole comandate</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Descriere</th>
                                    <th class="text-end">Cantitate</th>
                                    <th class="text-end">Recepționat</th>
                                    <th class="text-end">Preț unitar</th>
                                    <th class="text-end">Valoare linie</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchaseOrder->items as $item)
                                    <tr>
                                        <td>
                                            {{ $item->description ?? $item->produs?->nume ?? '—' }}
                                            @if($item->produs)
                                                <div class="text-muted small">Produs: {{ $item->produs->nume }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($item->received_quantity, 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($item->unit_price, 2, ',', '.') }} lei</td>
                                        <td class="text-end">{{ number_format($item->line_total, 2, ',', '.') }} lei</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($purchaseOrder->notes)
                        <div class="alert alert-secondary mt-3">
                            <strong>Note comandă:</strong>
                            <p class="mb-0">{!! nl2br(e($purchaseOrder->notes)) !!}</p>
                        </div>
                    @endif
                </div>
            </div>

            @if($purchaseOrder->status !== PurchaseOrder::STATUS_RECEIVED)
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Marchează ca recepționată</h2>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <p class="mb-2"><strong>Completează corect datele de recepție.</strong></p>
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('procurement.purchase-orders.receive', $purchaseOrder) }}">
                            @csrf
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="received_at">Data și ora recepției</label>
                                    <input type="datetime-local" class="form-control" id="received_at" name="received_at" value="{{ old('received_at', Carbon::now()->format('Y-m-d\TH:i')) }}">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" value="1" id="create_movements" name="create_movements" @checked(old('create_movements'))>
                                        <label class="form-check-label" for="create_movements">
                                            Creează mișcări de stoc pentru produsele asociate
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Cantități recepționate</label>
                                <p class="small text-muted">Introduce cantitatea totală recepționată pentru fiecare linie (inclusiv recepțiile anterioare, dacă există).</p>
                                @foreach($purchaseOrder->items as $item)
                                    <div class="border rounded-3 p-3 mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>{{ $item->description ?? $item->produs?->nume ?? 'Linie comandă' }}</strong>
                                            <span class="badge bg-light text-dark">Comandat: {{ number_format($item->quantity, 2, ',', '.') }}</span>
                                        </div>
                                        <div class="row g-3 align-items-end">
                                            <div class="col-md-6">
                                                <label class="form-label">Cantitate recepționată totală</label>
                                                <input type="number" step="0.01" min="0" class="form-control" name="items[{{ $item->id }}][received_quantity]" value="{{ old('items.' . $item->id . '.received_quantity', $item->quantity) }}">
                                            </div>
                                            <div class="col-md-6">
                                                <p class="text-muted small mb-0">Înregistrat deja: {{ number_format($item->received_quantity, 2, ',', '.') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="receive_notes">Note adiționale</label>
                                <textarea class="form-control" id="receive_notes" name="notes" rows="3" placeholder="Note interne pentru recepție">{{ old('notes') }}</textarea>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa-solid fa-check me-1"></i> Marchează recepția
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
