@extends('layouts.app')

@section('content')
<div class="dashboard-shell mx-3">
    @include ('errors.errors')

    @php
        $maxStat = max(1, $cereriOfertaDeschise, $comenziIntarziate, $comenziInExecutie, $comenziActive);
        $barCereri = round(($cereriOfertaDeschise / $maxStat) * 100);
        $barIntarziate = round(($comenziIntarziate / $maxStat) * 100);
        $barExecutie = round(($comenziInExecutie / $maxStat) * 100);
        $barActive = round(($comenziActive / $maxStat) * 100);
    @endphp

    <div class="dashboard-hero mb-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <span class="dashboard-pill">
                    <i class="fa-solid fa-chart-line me-2"></i> Panou acasa
                </span>
                <h3 class="mb-2">Situatie curenta comenzi</h3>
                <p class="mb-0 text-muted">Statistici rapide si grafice pentru monitorizare zilnica.</p>
            </div>
            <div class="dashboard-highlight text-end">
                <div class="dashboard-highlight-label">Comenzi active</div>
                <div class="dashboard-highlight-value">{{ $comenziActive }}</div>
                <div class="dashboard-highlight-sub">Fara stari finale.</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-4">
            <div class="stat-card accent-rose h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Cereri oferta deschise</h6>
                        <p class="stat-sub">Cereri active fara stari finale.</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-file-circle-question"></i>
                    </div>
                </div>
                <div class="stat-value">{{ $cereriOfertaDeschise }}</div>
                <div class="stat-footer">
                    <a class="btn btn-sm stat-btn" href="{{ route('cereri-oferta') }}">
                        Vezi cereri oferta
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card accent-amber h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Comenzi intarziate</h6>
                        <p class="stat-sub">Comenzi cu termen depasit.</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
                <div class="stat-value">{{ $comenziIntarziate }}</div>
                <div class="stat-footer">
                    <a class="btn btn-sm stat-btn" href="{{ route('comenzi.index', ['overdue' => 1]) }}">
                        Vezi intarziate
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card accent-slate h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Comenzi in executie</h6>
                        <p class="stat-sub">Comenzi in productie curenta.</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fa-solid fa-industry"></i>
                    </div>
                </div>
                <div class="stat-value">{{ $comenziInExecutie }}</div>
                <div class="stat-footer">
                    <a class="btn btn-sm stat-btn" href="{{ route('comenzi.index', ['status' => 'in_executie']) }}">
                        Vezi in executie
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-12">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Grafic comparativ</h5>
                            <p class="mb-0 text-muted">Indicatori principali pentru fluxul de comenzi.</p>
                        </div>
                        <span class="dashboard-badge">KPI live</span>
                    </div>
                    <div class="chart-rows">
                        <div class="chart-row accent-rose">
                            <div class="chart-label">Cereri oferta</div>
                            <div class="chart-bar">
                                <span class="chart-bar-fill" style="width: {{ $barCereri }}%;"></span>
                            </div>
                            <div class="chart-value">{{ $cereriOfertaDeschise }}</div>
                        </div>
                        <div class="chart-row accent-amber">
                            <div class="chart-label">Comenzi intarziate</div>
                            <div class="chart-bar">
                                <span class="chart-bar-fill" style="width: {{ $barIntarziate }}%;"></span>
                            </div>
                            <div class="chart-value">{{ $comenziIntarziate }}</div>
                        </div>
                        <div class="chart-row accent-slate">
                            <div class="chart-label">Comenzi in executie</div>
                            <div class="chart-bar">
                                <span class="chart-bar-fill" style="width: {{ $barExecutie }}%;"></span>
                            </div>
                            <div class="chart-value">{{ $comenziInExecutie }}</div>
                        </div>
                        <div class="chart-row accent-forest">
                            <div class="chart-label">Comenzi active</div>
                            <div class="chart-bar">
                                <span class="chart-bar-fill" style="width: {{ $barActive }}%;"></span>
                            </div>
                            <div class="chart-value">{{ $comenziActive }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
