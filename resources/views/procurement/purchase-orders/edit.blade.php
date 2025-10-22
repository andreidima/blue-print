@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Editează comanda #{{ $purchaseOrder->po_number }}</h1>
            <p class="text-muted mb-0">Actualizează detaliile înainte de a trimite sau recepționa comanda.</p>
        </div>
        <a href="{{ route('procurement.purchase-orders.show', $purchaseOrder) }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-eye me-1"></i> Vezi detalii
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <p class="mb-2"><strong>Formularul conține erori.</strong></p>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('procurement.purchase-orders.update', $purchaseOrder) }}">
        @csrf
        @method('PUT')
        @include('procurement.purchase-orders.form', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => $suppliers,
            'products' => $products,
            'statuses' => $statuses,
        ])
    </form>
</div>
@endsection
