@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">
                    <i class="fa-solid fa-database me-2"></i>
                    Migrații bază de date
                </h1>
                <p class="text-muted mb-0">Monitorizează ce migrații au rulat deja și ce schimbări urmează să fie aplicate.</p>
            </div>
            <div class="text-md-end">
                <span class="badge bg-secondary fs-6">Următorul batch: {{ $nextBatch }}</span>
            </div>
        </div>

        @if (session('migrationStatus'))
            @php($status = session('migrationStatus'))
            <div class="alert alert-{{ $status['type'] ?? 'info' }} shadow-sm">
                <div class="d-flex align-items-start">
                    <div class="me-2">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <div>
                        <strong class="d-block mb-1">{{ $status['message'] ?? 'Status migrații actualizat.' }}</strong>
                        @if (!empty($status['output']))
                            <details>
                                <summary class="text-decoration-underline">Vezi jurnalul comenzii</summary>
                                <pre class="bg-dark text-light rounded mt-2 p-3 small">{{ trim($status['output']) }}</pre>
                            </details>
                        @endif
                    </div>
                </div>
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

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted">Total migrații</h2>
                        <p class="display-6 mb-0">{{ $totals['total'] }}</p>
                        <p class="text-muted small mb-0">Fișiere găsite în proiect.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted">Migrații rulate</h2>
                        <p class="display-6 mb-0 text-success">{{ $totals['ran'] }}</p>
                        <p class="text-muted small mb-0">Ultimul batch rulat: {{ $lastBatch ?? '—' }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted">Migrații în așteptare</h2>
                        <p class="display-6 mb-0 text-warning">{{ $totals['pending'] }}</p>
                        <p class="text-muted small mb-0">Revizuiește detaliile înainte de rulare.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Migrații în așteptare</h2>
            </div>
            <div class="card-body">
                @if ($pretendError)
                    <div class="alert alert-warning">
                        <strong>Previzualizarea SQL nu a putut fi generată.</strong>
                        <div class="small text-muted">{{ $pretendError }}</div>
                    </div>
                @endif

                @if ($pendingMigrations->isEmpty())
                    <p class="text-success mb-0">
                        <i class="fa-solid fa-circle-check me-1"></i>
                        Nu există migrații care așteaptă să fie rulate.
                    </p>
                @else
                    <p class="text-muted">Aceste migrații vor rula în batch-ul <strong>{{ $nextBatch }}</strong>. SQL-ul de mai jos provine din rularea comenzii <code>php artisan migrate --pretend</code>.</p>
                    <div class="accordion" id="pendingMigrations">
                        @foreach ($pendingMigrations as $index => $migration)
                            @php($sqlPreview = $pretendByMigration[$migration['name']] ?? [])
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading-{{ $index }}">
                                    <button class="accordion-button {{ $index === 0 ? '' : 'collapsed' }}" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapse-{{ $index }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                                        aria-controls="collapse-{{ $index }}">
                                        <span class="fw-semibold">{{ $migration['name'] }}</span>
                                        <span class="ms-2 badge bg-light text-dark">{{ $migration['headline'] }}</span>
                                    </button>
                                </h2>
                                <div id="collapse-{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                                    aria-labelledby="heading-{{ $index }}" data-bs-parent="#pendingMigrations">
                                    <div class="accordion-body">
                                        <p class="mb-2"><strong>Fișier:</strong> <code>{{ $migration['path'] }}</code></p>
                                        @if (!empty($sqlPreview))
                                            <p class="mb-2"><strong>SQL estimat:</strong></p>
                                            <pre class="bg-dark text-light p-3 rounded small mb-0">{{ implode("\n", $sqlPreview) }}</pre>
                                        @else
                                            <p class="text-muted small mb-0">Nu există o previzualizare SQL disponibilă pentru această migrație.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Migrații deja rulate</h2>
            </div>
            <div class="card-body">
                @if ($ranMigrations->isEmpty())
                    <p class="text-muted mb-0">Încă nu a fost rulată nicio migrație.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Batch</th>
                                    <th scope="col">Migrație</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ranMigrations as $migration)
                                    <tr>
                                        <td class="fw-semibold">{{ $migration->batch }}</td>
                                        <td><code>{{ $migration->migration }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="card border-warning shadow-sm">
            <div class="card-body">
                <h2 class="h5 text-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i> Rulează migrațiile cu grijă</h2>
                <p class="mb-3">Asigură-te că există un backup recent și că ai înțeles schimbările de mai sus înainte de a continua. Această acțiune rulează <code>php artisan migrate --force</code> în producție.</p>
                <form method="POST" action="{{ route('tech.migrations.run') }}" class="row gy-2">
                    @csrf
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" name="confirm_run" id="confirm_run" required>
                            <label class="form-check-label" for="confirm_run">
                                Confirm că am verificat previzualizarea și am un backup actualizat.
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-danger">
                            <i class="fa-solid fa-play me-2"></i> Rulează migrațiile acum
                        </button>
                    </div>
                </form>
                <p class="small text-muted mt-3 mb-0">După rulare, rezultatul comenzii și eventualele erori vor apărea mai sus în această pagină.</p>
            </div>
        </div>
    </div>
@endsection
