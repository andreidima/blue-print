@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Comandă de achiziție nouă</h1>
            <p class="text-muted mb-0">Înregistrează detaliile furnizorului și articolele comandate.</p>
        </div>
        <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Înapoi la listă
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

    <form method="POST" action="{{ route('procurement.purchase-orders.store') }}">
        @csrf
        @include('procurement.purchase-orders.form', [
            'suppliers' => $suppliers,
            'products' => $products,
            'statuses' => $statuses,
        ])
    </form>
</div>
@endsection
