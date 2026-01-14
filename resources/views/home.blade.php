@extends('layouts.app')

@section('content')
<div class="mx-3 px-3">
    @include ('errors.errors')

    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card shadow-sm">
                <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between">
                    <div class="mb-3 mb-lg-0">
                        <h4 class="mb-1">Cereri de oferta deschise</h4>
                        <p class="mb-0 text-muted">Urmarire cereri active care nu sunt in stari finale.</p>
                    </div>
                    <div class="display-4 fw-bold text-end">
                        {{ $cereriOfertaDeschise }}
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a class="btn btn-sm btn-primary" href="{{ route('cereri-oferta') }}">
                        Vezi cereri oferta
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">Comenzi intarziate</h5>
                            <p class="mb-0 text-muted">Comenzi care au depasit timpul estimat.</p>
                        </div>
                        <div class="display-5 fw-bold text-danger">{{ $comenziIntarziate }}</div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a class="btn btn-sm btn-outline-danger" href="{{ route('comenzi.index', ['overdue' => 1]) }}">
                        Vezi intarziate
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">Comenzi in executie</h5>
                            <p class="mb-0 text-muted">Comenzi in stadiul de productie.</p>
                        </div>
                        <div class="display-5 fw-bold">{{ $comenziInExecutie }}</div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('comenzi.index', ['status' => 'in_executie']) }}">
                        Vezi in executie
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
