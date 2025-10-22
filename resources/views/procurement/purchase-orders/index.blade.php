@extends('layouts.app')

@php
    use App\Models\Procurement\PurchaseOrder;
@endphp

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Comenzi de achiziție</h1>
            <p class="text-muted mb-0">Gestionează relația cu furnizorii și urmărește recepțiile planificate.</p>
        </div>
        <a href="{{ route('procurement.purchase-orders.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i> Comandă nouă
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="row g-3 align-items-end mb-4">
        <div class="col-md-4">
            <label class="form-label" for="status_filter">Filtrează după status</label>
            <select class="form-select" id="status_filter" name="status">
                <option value="">Toate statusurile</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected($activeStatus === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn btn-outline-secondary">Aplică</button>
        </div>
        @if($activeStatus)
            <div class="col-md-auto">
                <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-link">Resetează</a>
            </div>
        @endif
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Număr PO</th>
                    <th>Furnizor</th>
                    <th>Status</th>
                    <th>Data estimată</th>
                    <th class="text-end">Valoare totală</th>
                    <th class="text-center">Acțiuni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseOrders as $order)
                    <tr>
                        <td>
                            <a href="{{ route('procurement.purchase-orders.show', $order) }}" class="fw-semibold">
                                {{ $order->po_number }}
                            </a>
                        </td>
                        <td>{{ $order->supplier?->name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $order->status === PurchaseOrder::STATUS_RECEIVED ? 'success' : ($order->status === PurchaseOrder::STATUS_CANCELLED ? 'secondary' : 'info') }}">
                                {{ ucfirst($order->status) }}
                            </span>
                        </td>
                        <td>
                            @if($order->expected_at)
                                {{ $order->expected_at->format('d.m.Y') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($order->total_value, 2, ',', '.') }} lei</td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="{{ route('procurement.purchase-orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="{{ route('procurement.purchase-orders.edit', $order) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form method="POST" action="{{ route('procurement.purchase-orders.destroy', $order) }}" onsubmit="return confirm('Sigur dorești să ștergi această comandă?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            Nu există încă comenzi de achiziție. Creează una nouă pentru a începe să folosești modulul.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $purchaseOrders->links() }}
</div>
@endsection
