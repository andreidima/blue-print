@extends ('layouts.app')

@section('content')
@php
    $statusPlataOptions = \App\Enums\StatusPlata::options();
    $currentStatus = old('status', $comanda->status);
    $currentTimp = old('timp_estimat_livrare', optional($comanda->timp_estimat_livrare)->format('Y-m-d\\TH:i'));
    $currentTipar = old('necesita_tipar_exemplu', $comanda->necesita_tipar_exemplu);
    $currentFrontdesk = old('frontdesk_user_id', $comanda->frontdesk_user_id);
    $currentSupervizor = old('supervizor_user_id', $comanda->supervizor_user_id);
    $currentGrafician = old('grafician_user_id', $comanda->grafician_user_id);
    $currentExecutant = old('executant_user_id', $comanda->executant_user_id);
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-8">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-clipboard-list me-1"></i> Comanda #{{ $comanda->id }}
            </span>
            <span class="badge bg-secondary">{{ $statusuri[$comanda->status] ?? $comanda->status }}</span>
            @if ($comanda->is_overdue)
                <span class="badge bg-danger">Intarziata</span>
            @elseif ($comanda->is_due_soon)
                <span class="badge bg-warning text-dark">In urmatoarele 24h</span>
            @endif
            @if ($comanda->finalizat_la)
                <span class="badge {{ $comanda->is_late ? 'bg-danger' : 'bg-success' }}">
                    Finalizat {{ $comanda->finalizat_la->format('d.m.Y H:i') }}
                </span>
            @endif
        </div>
        <div class="col-lg-4 text-end">
            <form method="POST" action="{{ route('comenzi.destroy', $comanda) }}" class="d-inline" onsubmit="return confirm('Sigur vrei sa stergi aceasta comanda?')">
                @method('DELETE')
                @csrf
                <button type="submit" class="btn btn-sm btn-danger text-white border border-dark rounded-3 me-2">
                    <i class="fa-solid fa-trash me-1"></i> Sterge
                </button>
            </form>
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        <form method="POST" action="{{ route('comenzi.update', $comanda) }}">
            @csrf
            @method('PUT')
            <div class="row mb-4">
                <div class="col-lg-4 mb-3">
                    <div class="p-3 rounded-3 bg-light">
                        <h6 class="mb-2">Client</h6>
                        <div class="mb-1"><strong>Nume:</strong> {{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                        <div class="mb-1"><strong>Telefon:</strong> {{ optional($comanda->client)->telefon ?? '-' }}</div>
                        <div class="mb-1"><strong>Email:</strong> {{ optional($comanda->client)->email ?? '-' }}</div>
                        <div><strong>Adresa:</strong> {{ optional($comanda->client)->adresa ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-lg-8 mb-3">
                    <div class="row">
                        <div class="col-lg-4 mb-3">
                            <label for="tip" class="mb-0 ps-3">Tip</label>
                            <input type="text" class="form-control bg-white rounded-3" value="{{ $tipuri[$comanda->tip] ?? $comanda->tip }}" readonly>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label for="sursa" class="mb-0 ps-3">Sursa</label>
                            <input type="text" class="form-control bg-white rounded-3" value="{{ $surse[$comanda->sursa] ?? $comanda->sursa }}" readonly>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label for="status" class="mb-0 ps-3">Status</label>
                            <select class="form-select bg-white rounded-3 {{ $errors->has('status') ? 'is-invalid' : '' }}" name="status" id="status">
                                @foreach ($statusuri as $key => $label)
                                    <option value="{{ $key }}" {{ $currentStatus === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label for="timp_estimat_livrare" class="mb-0 ps-3">Timp estimat livrare</label>
                            <input
                                type="datetime-local"
                                class="form-control bg-white rounded-3 {{ $errors->has('timp_estimat_livrare') ? 'is-invalid' : '' }}"
                                name="timp_estimat_livrare"
                                id="timp_estimat_livrare"
                                value="{{ $currentTimp }}">
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label class="mb-0 ps-3">Status plata</label>
                            <input type="text" class="form-control bg-white rounded-3" value="{{ $statusPlataOptions[$comanda->status_plata] ?? $comanda->status_plata }}" readonly>
                        </div>
                        <div class="col-lg-4 mb-3 d-flex align-items-center">
                            <div class="form-check mt-4 ps-4">
                                <input class="form-check-input" type="checkbox" name="necesita_tipar_exemplu" id="necesita_tipar_exemplu" value="1"
                                    {{ $currentTipar ? 'checked' : '' }}>
                                <label class="form-check-label" for="necesita_tipar_exemplu">Necesita tipar exemplu</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 mb-3">
                    <label for="frontdesk_user_id" class="mb-0 ps-3">Frontdesk</label>
                    <select class="form-select bg-white rounded-3" name="frontdesk_user_id" id="frontdesk_user_id">
                        <option value="">-</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ (string) $currentFrontdesk === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="supervizor_user_id" class="mb-0 ps-3">Supervizor</label>
                    <select class="form-select bg-white rounded-3" name="supervizor_user_id" id="supervizor_user_id">
                        <option value="">-</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ (string) $currentSupervizor === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="grafician_user_id" class="mb-0 ps-3">Grafician</label>
                    <select class="form-select bg-white rounded-3" name="grafician_user_id" id="grafician_user_id">
                        <option value="">-</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ (string) $currentGrafician === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="executant_user_id" class="mb-0 ps-3">Executant</label>
                    <select class="form-select bg-white rounded-3" name="executant_user_id" id="executant_user_id">
                        <option value="">-</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ (string) $currentExecutant === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-4 mb-3">
                    <label for="nota_frontdesk" class="mb-0 ps-3">Nota frontdesk</label>
                    <textarea class="form-control bg-white rounded-3" name="nota_frontdesk" id="nota_frontdesk" rows="4">{{ old('nota_frontdesk', $comanda->nota_frontdesk) }}</textarea>
                </div>
                <div class="col-lg-4 mb-3">
                    <label for="nota_grafician" class="mb-0 ps-3">Nota grafician</label>
                    <textarea class="form-control bg-white rounded-3" name="nota_grafician" id="nota_grafician" rows="4">{{ old('nota_grafician', $comanda->nota_grafician) }}</textarea>
                </div>
                <div class="col-lg-4 mb-3">
                    <label for="nota_executant" class="mb-0 ps-3">Nota executant</label>
                    <textarea class="form-control bg-white rounded-3" name="nota_executant" id="nota_executant" rows="4">{{ old('nota_executant', $comanda->nota_executant) }}</textarea>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-12 d-flex justify-content-center">
                    <button type="submit" class="btn btn-primary text-white rounded-3">
                        <i class="fa-solid fa-save me-1"></i> Salveaza modificarile
                    </button>
                </div>
            </div>
        </form>

        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="p-3 rounded-3 bg-light d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('comenzi.trimite-sms', $comanda) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-message me-1"></i> Trimite SMS
                        </button>
                    </form>
                    <form method="POST" action="{{ route('comenzi.trimite-email', $comanda) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-envelope me-1"></i> Trimite Email
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-12">
                <h6 class="mb-3">Produse</h6>
                <div class="table-responsive rounded">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Produs</th>
                                <th width="15%">Cantitate</th>
                                <th width="15%">Pret unitar</th>
                                <th width="15%">Total linie</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($comanda->produse as $linie)
                                <tr>
                                    <td>{{ $linie->produs->denumire ?? '-' }}</td>
                                    <td>{{ $linie->cantitate }}</td>
                                    <td>{{ number_format($linie->pret_unitar, 2) }}</td>
                                    <td>{{ number_format($linie->total_linie, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Nu exista produse adaugate.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('comenzi.produse.store', $comanda) }}">
                    @csrf
                    <div class="row align-items-end">
                        <div class="col-lg-8 mb-2">
                            <label class="mb-0 ps-3">Produs</label>
                            <select class="form-select bg-white rounded-3" name="produs_id">
                                <option value="">Selecteaza produs</option>
                                @foreach ($produse as $produs)
                                    <option value="{{ $produs->id }}">{{ $produs->denumire }} ({{ number_format($produs->pret, 2) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="mb-0 ps-3">Cantitate</label>
                            <input type="number" min="1" class="form-control bg-white rounded-3" name="cantitate" value="1">
                        </div>
                        <div class="col-lg-2 mb-2 text-end">
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                <i class="fa-solid fa-plus me-1"></i> Adauga
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6 mb-3">
                <h6 class="mb-3">Atasamente</h6>
                <form method="POST" action="{{ route('comenzi.atasamente.store', $comanda) }}" enctype="multipart/form-data" class="mb-3">
                    @csrf
                    <div class="input-group">
                        <input type="file" class="form-control" name="atasament" required>
                        <button type="submit" class="btn btn-outline-primary">Incarca</button>
                    </div>
                </form>
                <ul class="list-group">
                    @forelse ($comanda->atasamente as $atasament)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ $atasament->fileUrl() }}" target="_blank">{{ $atasament->original_name }}</a>
                            <span class="small text-muted">{{ number_format($atasament->size / 1024, 1) }} KB</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Nu exista atasamente.</li>
                    @endforelse
                </ul>
            </div>
            <div class="col-lg-6 mb-3">
                <h6 class="mb-3">Mockup-uri</h6>
                <form method="POST" action="{{ route('comenzi.mockupuri.store', $comanda) }}" enctype="multipart/form-data" class="mb-3">
                    @csrf
                    <div class="mb-2">
                        <input type="file" class="form-control" name="mockup" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" class="form-control" name="comentariu" placeholder="Comentariu (optional)">
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Incarca</button>
                </form>
                <ul class="list-group">
                    @forelse ($comanda->mockupuri as $mockup)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ $mockup->fileUrl() }}" target="_blank">{{ $mockup->original_name }}</a>
                                <span class="small text-muted">{{ number_format($mockup->size / 1024, 1) }} KB</span>
                            </div>
                            @if ($mockup->comentariu)
                                <div class="small text-muted mt-1">{{ $mockup->comentariu }}</div>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Nu exista mockup-uri.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-12">
                <h6 class="mb-3">Plati</h6>
                <div class="table-responsive rounded mb-3">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Suma</th>
                                <th>Metoda</th>
                                <th>Factura</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($comanda->plati as $plata)
                                <tr>
                                    <td>{{ optional($plata->platit_la)->format('d.m.Y H:i') }}</td>
                                    <td>{{ number_format($plata->suma, 2) }}</td>
                                    <td>{{ $metodePlata[$plata->metoda] ?? $plata->metoda }}</td>
                                    <td>{{ $plata->numar_factura }}</td>
                                    <td>{{ $plata->note }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Nu exista plati.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-4">
                        <div class="p-2 bg-light rounded-3">
                            <strong>Total:</strong> {{ number_format($comanda->total, 2) }}
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="p-2 bg-light rounded-3">
                            <strong>Total platit:</strong> {{ number_format($comanda->total_platit, 2) }}
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="p-2 bg-light rounded-3">
                            <strong>Status plata:</strong> {{ $statusPlataOptions[$comanda->status_plata] ?? $comanda->status_plata }}
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('comenzi.plati.store', $comanda) }}">
                    @csrf
                    <div class="row align-items-end">
                        <div class="col-lg-2 mb-2">
                            <label class="mb-0 ps-3">Suma</label>
                            <input type="number" step="0.01" min="0.01" class="form-control bg-white rounded-3" name="suma" required>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="mb-0 ps-3">Metoda</label>
                            <select class="form-select bg-white rounded-3" name="metoda" required>
                                @foreach ($metodePlata as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="mb-0 ps-3">Factura</label>
                            <input type="text" class="form-control bg-white rounded-3" name="numar_factura">
                        </div>
                        <div class="col-lg-3 mb-2">
                            <label class="mb-0 ps-3">Platit la</label>
                            <input type="datetime-local" class="form-control bg-white rounded-3" name="platit_la" value="{{ now()->format('Y-m-d\\TH:i') }}" required>
                        </div>
                        <div class="col-lg-3 mb-2">
                            <label class="mb-0 ps-3">Note</label>
                            <input type="text" class="form-control bg-white rounded-3" name="note">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12 text-end">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-plus me-1"></i> Adauga plata
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
