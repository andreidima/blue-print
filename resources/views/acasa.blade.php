@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body d-flex flex-column flex-xl-row align-items-xl-center justify-content-between">
                    <div class="mb-3 mb-xl-0">
                        <h4 class="mb-1">Bine ai revenit, <span class="text-primary fw-semibold">{{ auth()->user()->name ?? 'coleg' }}</span>!</h4>
                        <p class="mb-0 text-muted">
                            Monitorizează rapid fluxul dintre vânzări, producție și aprovizionare. Datele se actualizează automat
                            din WooCommerce, mișcările de stoc și comenzile către furnizori.
                        </p>
                    </div>
                    <div class="btn-group flex-wrap" role="group" aria-label="Acces rapid module">
                        <a
                            href="{{ $moduleLinks['orders'] ?? '#' }}"
                            class="btn btn-primary text-white mb-2 mb-xl-0 {{ $moduleLinks['orders'] ? '' : 'disabled' }}">
                            <i class="fa-solid fa-store me-1"></i> Comenzi site
                        </a>
                        <a
                            href="{{ $moduleLinks['inventory'] ?? '#' }}"
                            class="btn btn-outline-primary mb-2 mb-xl-0 {{ $moduleLinks['inventory'] ? '' : 'disabled' }}">
                            <i class="fa-solid fa-warehouse me-1"></i> Inventar
                        </a>
                        <a
                            href="{{ $moduleLinks['procurement'] ?? '#' }}"
                            class="btn btn-outline-secondary mb-2 mb-xl-0 {{ $moduleLinks['procurement'] ? '' : 'disabled' }}">
                            <i class="fa-solid fa-truck-arrow-right me-1"></i> Aprovizionare
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @php
            $totalLifecycleOrders = max(1, $orderMetrics['pending'] + $orderMetrics['processing'] + $orderMetrics['completed']);
            $completionRate = round(($orderMetrics['completed'] / $totalLifecycleOrders) * 100);
            $ordersChartData = json_encode([
                'pending' => $orderMetrics['pending'],
                'processing' => $orderMetrics['processing'],
                'completed' => $orderMetrics['completed'],
            ]);
            $inventoryChartData = json_encode([
                'inbound' => $inventoryMetrics['movements_last_7_days']['inbound_units'],
                'outbound' => $inventoryMetrics['movements_last_7_days']['outbound_units'],
            ]);
        @endphp

        <div class="col-12 col-xl-7">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Flux comenzi WooCommerce</h5>
                        <small class="text-muted">Status actualizări sincronizate</small>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Filtre comenzi">
                        <a href="{{ $moduleLinks['orders_pending'] ?? '#' }}" class="btn btn-outline-warning {{ $moduleLinks['orders_pending'] ? '' : 'disabled' }}">
                            <i class="fa-solid fa-hourglass-half me-1"></i> În așteptare (Plată)
                        </a>

                        <a href="{{ $moduleLinks['orders_blocked'] ?? '#' }}" class="btn btn-outline-danger {{ $moduleLinks['orders_blocked'] ? '' : 'disabled' }}">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> În așteptare
                        </a>
                        <a href="{{ $moduleLinks['orders_completed'] ?? '#' }}" class="btn btn-outline-success {{ $moduleLinks['orders_completed'] ? '' : 'disabled' }}">
                            <i class="fa-solid fa-check me-1"></i> Finalizate
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-6 col-md-3">
                            <p class="text-muted text-uppercase small mb-1">În așteptare (Plată)</p>
                            <div class="display-6 fw-semibold">{{ number_format($orderMetrics['pending']) }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <p class="text-muted text-uppercase small mb-1">În lucru</p>
                            <div class="display-6 fw-semibold">{{ number_format($orderMetrics['processing']) }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <p class="text-muted text-uppercase small mb-1">Finalizate</p>
                            <div class="display-6 fw-semibold text-success">{{ number_format($orderMetrics['completed']) }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <p class="text-muted text-uppercase small mb-1">Vânzări (30 zile)</p>
                            <div class="h4 fw-semibold text-primary">{{ number_format($orderMetrics['sales_last_30_days'], 2, ',', '.') }} lei</div>
                        </div>
                    </div>

                    <div class="row align-items-center mt-4">
                        <div class="col-md-6">
                            <canvas id="orderStatusChart" height="180"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 d-flex flex-column justify-content-between bg-light">
                                <div>
                                    <span class="text-muted text-uppercase small">Rată de finalizare</span>
                                    <div class="d-flex align-items-center justify-content-between mt-1">
                                        <span class="h4 mb-0 fw-semibold">{{ $completionRate }}%</span>
                                        <span class="text-muted small">{{ number_format($orderMetrics['completed']) }} din {{ number_format($totalLifecycleOrders) }} comenzi</span>
                                    </div>
                                    <div class="progress mt-3" style="height: 8px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $completionRate }}%"></div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <span class="text-muted text-uppercase small">Total vânzări curente</span>
                                    <div class="h5 fw-semibold mb-0">{{ number_format($orderMetrics['total_sales_value'], 2, ',', '.') }} lei</div>
                                    @if($orderMetrics['oldest_open_order'])
                                        <small class="text-muted">Cea mai veche comandă deschisă: {{ $orderMetrics['oldest_open_order']->diffForHumans() }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="alert alert-{{ $orderMetrics['blocked'] ? 'danger' : 'success' }} d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ number_format($orderMetrics['blocked']) }}</strong>
                                {{ $orderMetrics['blocked'] === 1 ? 'comandă așteaptă' : 'comenzi așteaptă' }} stoc.
                                <br>
                                <span class="text-muted small">Coordonează-te cu depozitul pentru a elibera blocajele.</span>
                            </div>
                            <a href="{{ $moduleLinks['orders_blocked'] ?? '#' }}" class="btn btn-sm btn-outline-light text-dark {{ $moduleLinks['orders_blocked'] ? '' : 'disabled' }}">
                                Deschide comenzi blocate
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Alertă stoc scăzut</h5>
                    <a href="{{ $moduleLinks['inventory'] ?? '#' }}" class="btn btn-sm btn-outline-primary {{ $moduleLinks['inventory'] ? '' : 'disabled' }}">
                        <i class="fa-solid fa-box-open me-1"></i> Gestionează stoc
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($lowStock->isEmpty())
                        <p class="text-center text-muted py-4 mb-0">Nicio alertă de stoc scăzut.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Produs</th>
                                        <th class="text-end">Stoc</th>
                                        <th class="text-end">Prag</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lowStock as $p)
                                        <tr>
                                            <td class="fw-semibold">{{ $p->nume }}</td>
                                            <td class="text-end">{{ $p->cantitate }}</td>
                                            <td class="text-end">{{ $p->prag_minim }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Inventar & mișcări recente</h5>
                        <small class="text-muted">Monitorizare stoc activă</small>
                    </div>
                    <a href="{{ $moduleLinks['movements'] ?? '#' }}" class="btn btn-sm btn-outline-secondary {{ $moduleLinks['movements'] ? '' : 'disabled' }}">
                        <i class="fa-solid fa-arrows-left-right me-1"></i> Mișcări
                    </a>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-6">
                            <span class="text-muted text-uppercase small">Stoc curent</span>
                            <div class="h3 fw-semibold mb-0">{{ number_format($inventoryMetrics['on_hand_units']) }} buc</div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted text-uppercase small">Valoare stoc</span>
                            <div class="h3 fw-semibold text-success mb-0">{{ number_format($inventoryMetrics['current_value'], 2, ',', '.') }} lei</div>
                        </div>
                    </div>

                    <div class="row mt-4 g-3 align-items-center">
                        <div class="col-md-6">
                            <canvas id="inventoryMovementChart" height="160"></canvas>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-group small">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Intrări 7 zile
                                    <span class="badge bg-success rounded-pill">{{ number_format($inventoryMetrics['movements_last_7_days']['inbound_units']) }}</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Ieșiri 7 zile
                                    <span class="badge bg-danger rounded-pill">{{ number_format($inventoryMetrics['movements_last_7_days']['outbound_units']) }}</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Produse sub prag
                                    <span class="badge bg-warning text-dark rounded-pill">{{ number_format($inventoryMetrics['low_stock_count']) }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="text-muted text-uppercase small mb-2">Ultimele ajustări</h6>
                        @if($inventoryMetrics['recent_movements']->isEmpty())
                            <p class="text-muted small mb-0">Nicio mișcare înregistrată.</p>
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach($inventoryMetrics['recent_movements'] as $movement)
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>{{ $movement->produs ?? 'Produs necunoscut' }}</strong>
                                            @if($movement->nr_comanda)
                                                <span class="badge bg-secondary ms-2">Comandă {{ $movement->nr_comanda }}</span>
                                            @endif
                                            <div class="text-muted small">{{ optional($movement->created_at)->diffForHumans() }}</div>
                                        </div>
                                        <span class="badge {{ $movement->delta >= 0 ? 'bg-success' : 'bg-danger' }} rounded-pill">
                                            {{ $movement->delta > 0 ? '+' : '' }}{{ $movement->delta }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Aprovizionare & comenzi furnizori</h5>
                        <small class="text-muted">Status comenzi active</small>
                    </div>
                    <a href="{{ $moduleLinks['procurement'] ?? '#' }}" class="btn btn-sm btn-outline-secondary {{ $moduleLinks['procurement'] ? '' : 'disabled' }}">
                        <i class="fa-solid fa-file-invoice me-1"></i> Deschide modul
                    </a>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-6">
                            <span class="text-muted text-uppercase small">Comenzi deschise</span>
                            <div class="h3 fw-semibold">{{ number_format($procurementMetrics['outstanding_count']) }}</div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted text-uppercase small">Valoare estimată</span>
                            <div class="h3 fw-semibold text-primary">{{ number_format($procurementMetrics['outstanding_value'], 2, ',', '.') }} lei</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="alert alert-{{ $procurementMetrics['overdue_count'] ? 'danger' : 'info' }}">
                            @if($procurementMetrics['overdue_count'])
                                <strong>{{ number_format($procurementMetrics['overdue_count']) }}</strong> comenzi sunt întârziate.
                                <br>
                                <span class="small text-muted">Prioritizează recepțiile pentru a debloca livrările.</span>
                            @else
                                Toate comenzile sunt în termen. Monitorizează recepțiile pentru a evita lipsa de stoc.
                            @endif
                        </div>
                        <div class="border rounded-3 p-3 bg-light">
                            <span class="text-muted text-uppercase small">Următoarea recepție estimată</span>
                            <div class="h5 fw-semibold mb-0">
                                @if($procurementMetrics['next_eta'])
                                    {{ $procurementMetrics['next_eta']->translatedFormat('d M Y') }}
                                @else
                                    Neconfirmat
                                @endif
                            </div>
                            <p class="small text-muted mb-0">Transmite statusul către vânzări dacă apar întârzieri.</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="text-muted text-uppercase small mb-2">Priorități departamente</h6>
                        <div class="row g-2">
                            <div class="col-sm-4">
                                <div class="border rounded-3 h-100 p-3 bg-white">
                                    <span class="badge bg-primary mb-2">Vânzări</span>
                                    <p class="small mb-2">Contactează clienții pentru <strong>{{ number_format($orderMetrics['blocked']) }}</strong> comenzi blocate.</p>
                                    @if(! empty($moduleLinks['orders_blocked']))
                                        <a href="{{ $moduleLinks['orders_blocked'] }}" class="small">Gestionează comenzi</a>
                                    @else
                                        <span class="small text-muted">Gestionează comenzi</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="border rounded-3 h-100 p-3 bg-white">
                                    <span class="badge bg-success mb-2">Depozit</span>
                                    <p class="small mb-2">Pregătește recepția pentru <strong>{{ number_format($procurementMetrics['outstanding_count']) }}</strong> PO active.</p>
                                    @if(! empty($moduleLinks['movements']))
                                        <a href="{{ $moduleLinks['movements'] }}" class="small">Vezi mișcări</a>
                                    @else
                                        <span class="small text-muted">Vezi mișcări</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="border rounded-3 h-100 p-3 bg-white">
                                    <span class="badge bg-warning text-dark mb-2">Aprovizionare</span>
                                    <p class="small mb-2">Confirmă termenele pentru recepția din
                                        <strong>
                                            {{ $procurementMetrics['next_eta'] ? $procurementMetrics['next_eta']->format('d.m') : 'N/A' }}
                                        </strong>.
                                    </p>
                                    @if(! empty($moduleLinks['procurement']))
                                        <a href="{{ $moduleLinks['procurement'] }}" class="small">Actualizează PO</a>
                                    @else
                                        <span class="small text-muted">Actualizează PO</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ordersDataset = {!! $ordersChartData !!};
        const orderCtx = document.getElementById('orderStatusChart');
        if (orderCtx) {
            new Chart(orderCtx, {
                type: 'doughnut',
                data: {
                    labels: ['În așteptare (Plată)', 'În lucru', 'Finalizate'],
                    datasets: [{
                        data: [ordersDataset.pending, ordersDataset.processing, ordersDataset.completed],
                        backgroundColor: ['#ffc107', '#0d6efd', '#198754'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                    },
                },
            });
        }

        const inventoryDataset = {!! $inventoryChartData !!};
        const inventoryCtx = document.getElementById('inventoryMovementChart');
        if (inventoryCtx) {
            new Chart(inventoryCtx, {
                type: 'bar',
                data: {
                    labels: ['Intrări', 'Ieșiri'],
                    datasets: [{
                        label: 'Unități',
                        data: [inventoryDataset.inbound, inventoryDataset.outbound],
                        backgroundColor: ['#20c997', '#dc3545'],
                        borderRadius: 6,
                        maxBarThickness: 40,
                    }],
                },
                options: {
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                        },
                    },
                },
            });
        }
    });
</script>

@endsection

