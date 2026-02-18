@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">
                    <i class="fa-solid fa-broom me-2"></i>
                    Curata cache aplicatie
                </h1>
                <p class="text-muted mb-0">Ruleaza <code>php artisan optimize:clear</code> din interfata, dupa deploy.</p>
            </div>
        </div>

        @if (session('cacheClearStatus'))
            @php
                $status = session('cacheClearStatus');
            @endphp
            <div class="alert alert-{{ $status['type'] ?? 'info' }} shadow-sm">
                <strong class="d-block mb-1">{{ $status['message'] ?? 'Status actualizat.' }}</strong>
                @if (!empty($status['output']))
                    <details>
                        <summary class="text-decoration-underline">Vezi output comanda</summary>
                        <pre class="bg-dark text-light rounded mt-2 p-3 small mb-0">{{ trim($status['output']) }}</pre>
                    </details>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card border-warning shadow-sm">
            <div class="card-body">
                <h2 class="h5 text-warning mb-3">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    Actiune de mentenanta
                </h2>
                <p class="mb-3">
                    Foloseste aceasta actiune doar cand este nevoie (ex: imediat dupa deploy).
                    Curata cache-ul de configurare, rute, view-uri compilate si alte optimizari runtime.
                </p>
                <form method="POST" action="{{ route('tech.cache.clear') }}">
                    @csrf
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" name="confirm_clear" id="confirm_clear" required>
                        <label class="form-check-label" for="confirm_clear">
                            Confirm ca vreau sa rulez comanda <code>optimize:clear</code> acum.
                        </label>
                    </div>
                    <button type="submit" class="btn btn-warning text-dark">
                        <i class="fa-solid fa-broom me-1"></i> Curata cache
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
