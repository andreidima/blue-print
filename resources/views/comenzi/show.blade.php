@extends ('layouts.app')

@section('content')
@php
    $statusPlataOptions = \App\Enums\StatusPlata::options();
    $currentUser = auth()->user();
    $canEditAssignments = $comanda->canEditAssignments($currentUser);
    $canEditNotaFrontdesk = $comanda->canEditNotaFrontdesk($currentUser);
    $canEditNotaGrafician = $comanda->canEditNotaGrafician($currentUser);
    $canEditNotaExecutant = $comanda->canEditNotaExecutant($currentUser);
    $produseCount = $comanda->produse->count();
    $platiCount = $comanda->plati->count();
    $atasamenteCount = $comanda->atasamente->count();
    $facturiCount = $comanda->facturi->count();
    $mockupCount = $comanda->mockupuri->count();
    $facturaEmailsCount = $comanda->facturaEmails->count();
    $balance = (float) $comanda->total - (float) $comanda->total_platit;
    $balanceIsSettled = abs($balance) < 0.01;
    $balanceIsCredit = $balance < 0 && ! $balanceIsSettled;
    $balanceLabel = $balanceIsSettled ? 'Achitat' : ($balanceIsCredit ? 'Credit' : 'Rest de plata');
    $balanceValue = $balanceIsSettled ? 0 : abs($balance);
    $balanceAccent = ($balanceIsSettled || $balanceIsCredit) ? 'accent-forest' : 'accent-amber';
    $clientTelefon = optional($comanda->client)->telefon;
    $clientTelefonLink = $clientTelefon ? preg_replace('/[^0-9+]/', '', $clientTelefon) : '';
    $clientEmail = optional($comanda->client)->email;
    $canManageFacturi = $comanda->canManageFacturi($currentUser);
    $clientName = trim(optional($comanda->client)->nume_complet ?? '');
    $appName = config('app.name');
    $subjectClientName = $clientName ?: 'Client';
    $orderLines = $comanda->produse
        ->map(function ($linie) {
            $nume = $linie->custom_denumire ?: optional($linie->produs)->denumire;
            $nume = trim((string) $nume);
            if ($nume === '') {
                $nume = 'Produs';
            }

            $cantitate = (int) $linie->cantitate;
            if ($cantitate <= 0) {
                $cantitate = 1;
            }

            return "- {$nume} x {$cantitate}";
        })
        ->filter()
        ->values();
    $orderSummary = $orderLines->isNotEmpty()
        ? "Rezumat comandă:\n" . $orderLines->implode("\n") . "\n\n"
        : '';
    $defaultFacturaSubject = "Factură {$appName} - {$subjectClientName} - comanda #{$comanda->id}";
    $defaultFacturaBody = "Bună ziua {$subjectClientName},\n\nAtașat găsiți factura {$appName} pentru comanda #{$comanda->id}.\n\n{$orderSummary}Vă mulțumim,\n{$appName}";
    $canSendFacturaEmail = $facturiCount > 0 && !empty($clientEmail);
    $currentClientId = old('client_id', $comanda->client_id);
    $initialClientLabel = '';
    if ((string) $currentClientId === (string) $comanda->client_id && $comanda->client) {
        $initialClientLabel = $comanda->client->nume_complet;
    }
    $currentStatus = old('status', $comanda->status);
    $currentTimp = old('timp_estimat_livrare', optional($comanda->timp_estimat_livrare)->format('Y-m-d\\TH:i'));
    $currentTipar = old('necesita_tipar_exemplu', $comanda->necesita_tipar_exemplu);
    $currentFrontdesk = old('frontdesk_user_id', $comanda->frontdesk_user_id);
    $currentSupervizor = old('supervizor_user_id', $comanda->supervizor_user_id);
    $currentGrafician = old('grafician_user_id', $comanda->grafician_user_id);
    $currentExecutant = old('executant_user_id', $comanda->executant_user_id);
    $currentSolicitareClient = old('solicitare_client', $comanda->solicitare_client);
    $currentCantitateComanda = old('cantitate_comanda', $comanda->cantitate);
    $currentProdusTip = old('produs_tip', 'existing');
    $currentProdusId = old('produs_id');
    $currentCustomDenumire = old('custom_denumire');
    $currentCustomPretUnitar = old('custom_pret_unitar');
    $currentLinieCantitate = old('cantitate', 1);
    $initialProdusLabel = '';
    if ($currentProdusId) {
        $matchedProdus = $produse->first(fn ($produs) => (string) $produs->id === (string) $currentProdusId);
        if ($matchedProdus) {
            $initialProdusLabel = $matchedProdus->denumire . ' (' . number_format($matchedProdus->pret, 2) . ')';
        }
    }

    $comandaSections = [
        ['id' => 'detalii', 'label' => 'Detalii comanda'],
        ['id' => 'necesar', 'label' => 'Necesar', 'count' => $produseCount],
        ['id' => 'atasamente', 'label' => 'Atasamente', 'count' => $atasamenteCount],
        ['id' => 'facturi', 'label' => 'Facturi', 'count' => $facturiCount],
        ['id' => 'mockupuri', 'label' => 'Mockup-uri', 'count' => $mockupCount],
        ['id' => 'plati', 'label' => 'Plati', 'count' => $platiCount],
        ['id' => 'etape', 'label' => 'Etape comanda'],
    ];
@endphp
<div class="mx-3 px-3 card comanda-shell">
    <div class="row card-header align-items-center comanda-header">
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
                <button type="submit" class="btn btn-sm btn-danger text-white rounded-3 shadow-sm me-2">
                    <i class="fa-solid fa-trash me-1"></i> Sterge
                </button>
            </form>
            <a class="btn btn-sm btn-outline-light rounded-3 shadow-sm" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-0 py-4">
        @include ('errors.errors')

        <div class="row g-3">
            <div class="col-lg-2 d-none d-lg-block">
                <div class="card border-0 bg-light sticky-top" style="top: 1rem;">
                    <div class="card-body p-3">
                        <div class="small text-muted mb-2">Navigare</div>
                        <div class="list-group list-group-flush comanda-nav">
                            @foreach ($comandaSections as $section)
                                <a class="list-group-item list-group-item-action bg-light d-flex justify-content-between align-items-center" href="#{{ $section['id'] }}" data-comanda-jump data-section-id="{{ $section['id'] }}">
                                    <span>{{ $section['label'] }}</span>
                                    @if (array_key_exists('count', $section))
                                        <span class="badge bg-white text-dark border rounded-pill">{{ $section['count'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-10">
                <div class="d-lg-none mb-3">
                    <label for="comanda-section-select" class="form-label mb-1">Sari la sectiune</label>
                    <select class="form-select" id="comanda-section-select">
                        <option value="">Alege...</option>
                        @foreach ($comandaSections as $section)
                            <option value="#{{ $section['id'] }}">
                                {{ $section['label'] }}{{ array_key_exists('count', $section) ? ' (' . $section['count'] . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div class="small text-muted">Sectiuni</div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Actiuni sectiuni">
                        <button type="button" class="btn btn-outline-secondary" data-comanda-action="expand">
                            <i class="fa-solid fa-square-plus me-1"></i> Extinde toate
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-comanda-action="collapse">
                            <i class="fa-solid fa-square-minus me-1"></i> Strange toate
                        </button>
                    </div>
                </div>

                <div class="accordion" id="comanda-accordion">
                    <div class="accordion-item js-comanda-section comanda-accordion-item" id="detalii" data-collapse="#collapse-detalii">
                        <h2 class="accordion-header" id="heading-detalii">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-detalii" aria-expanded="true" aria-controls="collapse-detalii">
                                <i class="fa-solid fa-circle-info me-2 text-primary"></i>
                                <span>Detalii comanda</span>
                            </button>
                        </h2>
                        <div id="collapse-detalii" class="accordion-collapse collapse show" aria-labelledby="heading-detalii">
                            <div class="accordion-body">
                                <form id="comanda-update-form" method="POST" action="{{ route('comenzi.update', $comanda) }}">
            @csrf
            @method('PUT')
            <div class="row mb-4">
                <div class="col-lg-4 mb-3">
                    <div class="p-3 rounded-3 bg-light">
                        <h6 class="mb-2">Client</h6>
                        <div class="mb-3">
                            <label class="form-label mb-0">Schimba client</label>
                            <div
                                class="js-client-selector"
                                data-name="client_id"
                                data-search-url="{{ route('clienti.select-options') }}"
                                data-store-url="{{ route('clienti.quick-store') }}"
                                data-initial-client-id="{{ $currentClientId }}"
                                data-initial-client-label="{{ $initialClientLabel }}"
                                data-invalid="{{ $errors->has('client_id') ? '1' : '0' }}"
                            ></div>
                        </div>
                        <hr class="my-3">
                        <div class="mb-1"><strong>Nume:</strong> {{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                        <div class="mb-1">
                            <strong>Tip:</strong>
                            {{ (optional($comanda->client)->type ?? 'pf') === 'pj' ? 'Persoana juridica' : 'Persoana fizica' }}
                        </div>
                        @if ((optional($comanda->client)->type ?? 'pf') === 'pj')
                            <div class="mb-1"><strong>CUI:</strong> {{ optional($comanda->client)->cui ?? '-' }}</div>
                        @else
                            <div class="mb-1"><strong>CNP:</strong> {{ optional($comanda->client)->cnp ?? '-' }}</div>
                        @endif
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
                        <div class="col-lg-12">
                            <div class="p-3 rounded-3 bg-light">
                                <h6 class="mb-2">Informatii comanda</h6>
                                <div class="row">
                                    <div class="col-lg-8 mb-0">
                                        <label for="solicitare_client" class="mb-0 ps-3">Solicitare client</label>
                                        <textarea class="form-control bg-white rounded-3" name="solicitare_client" id="solicitare_client" rows="4" {{ $canEditNotaFrontdesk ? '' : 'readonly' }}>{{ $currentSolicitareClient }}</textarea>
                                    </div>
                                    <div class="col-lg-4 mb-3">
                                        <label for="cantitate_comanda" class="mb-0 ps-3">Cantitate</label>
                                        <input type="number" min="1" class="form-control bg-white rounded-3" name="cantitate_comanda" id="cantitate_comanda" value="{{ $currentCantitateComanda }}" {{ $canEditNotaFrontdesk ? '' : 'readonly' }}>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-3 mb-3">
                    <label for="frontdesk_user_id" class="mb-0 ps-3">Frontdesk</label>
                    <select class="form-select bg-white rounded-3" name="frontdesk_user_id" id="frontdesk_user_id" {{ $canEditAssignments ? '' : 'disabled' }}>
                        <option value="">-</option>
                        @foreach ($frontdeskUsers as $user)
                            <option value="{{ $user->id }}" {{ (string) $currentFrontdesk === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="supervizor_user_id" class="mb-0 ps-3">Supervizor</label>
                    <select class="form-select bg-white rounded-3" name="supervizor_user_id" id="supervizor_user_id" {{ $canEditAssignments ? '' : 'disabled' }}>
                        <option value="">-</option>
                        @foreach ($supervizorUsers as $user)
                            <option value="{{ $user->id }}" {{ (string) $currentSupervizor === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="grafician_user_id" class="mb-0 ps-3">Grafician</label>
                    <select class="form-select bg-white rounded-3" name="grafician_user_id" id="grafician_user_id" {{ $canEditAssignments ? '' : 'disabled' }}>
                        <option value="">-</option>
                        @foreach ($graficianUsers as $user)
                            <option value="{{ $user->id }}" {{ (string) $currentGrafician === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 mb-3">
                    <label for="executant_user_id" class="mb-0 ps-3">Executant</label>
                    <select class="form-select bg-white rounded-3" name="executant_user_id" id="executant_user_id" {{ $canEditAssignments ? '' : 'disabled' }}>
                        <option value="">-</option>
                        @foreach ($executantUsers as $user)
                            <option value="{{ $user->id }}" {{ (string) $currentExecutant === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-4 mb-3">
                    <label for="nota_frontdesk" class="mb-0 ps-3">Nota frontdesk</label>
                    <textarea class="form-control bg-white rounded-3" name="nota_frontdesk" id="nota_frontdesk" rows="4" {{ $canEditNotaFrontdesk ? '' : 'readonly' }}>{{ old('nota_frontdesk', $comanda->nota_frontdesk) }}</textarea>
                </div>
                <div class="col-lg-4 mb-3">
                    <label for="nota_grafician" class="mb-0 ps-3">Nota grafician</label>
                    <textarea class="form-control bg-white rounded-3" name="nota_grafician" id="nota_grafician" rows="4" {{ $canEditNotaGrafician ? '' : 'readonly' }}>{{ old('nota_grafician', $comanda->nota_grafician) }}</textarea>
                </div>
                <div class="col-lg-4 mb-3">
                    <label for="nota_executant" class="mb-0 ps-3">Nota executant</label>
                    <textarea class="form-control bg-white rounded-3" name="nota_executant" id="nota_executant" rows="4" {{ $canEditNotaExecutant ? '' : 'readonly' }}>{{ old('nota_executant', $comanda->nota_executant) }}</textarea>
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
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item js-comanda-section comanda-accordion-item" id="necesar" data-collapse="#collapse-necesar">
                        <h2 class="accordion-header" id="heading-necesar">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-necesar" aria-expanded="false" aria-controls="collapse-necesar">
                                <i class="fa-solid fa-boxes-stacked me-2 text-success"></i>
                                <span>Necesar</span>
                            </button>
                        </h2>
                        <div id="collapse-necesar" class="accordion-collapse collapse" aria-labelledby="heading-necesar">
                            <div class="accordion-body">
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="table-responsive rounded">
                    <table class="table table-sm table-bordered align-middle table-hover">
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
                                    <td>{{ $linie->custom_denumire ?? ($linie->produs->denumire ?? '-') }}</td>
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
                        <div class="col-12 mb-2">
                            <label class="mb-0 ps-3">Tip produs</label>
                            <div class="btn-group" role="group" aria-label="Tip produs">
                                <input type="radio" class="btn-check" name="produs_tip" id="produs-tip-existing" value="existing" {{ $currentProdusTip === 'custom' ? '' : 'checked' }}>
                                <label class="btn btn-outline-secondary" for="produs-tip-existing">Din lista</label>
                                <input type="radio" class="btn-check" name="produs_tip" id="produs-tip-custom" value="custom" {{ $currentProdusTip === 'custom' ? 'checked' : '' }}>
                                <label class="btn btn-outline-secondary" for="produs-tip-custom">Produs custom</label>
                            </div>
                        </div>
                        <div class="col-lg-8 mb-2" data-produs-mode="existing">
                            <label class="mb-0 ps-3">Produs</label>
                            <div
                                class="js-product-selector"
                                data-name="produs_id"
                                data-search-url="{{ route('produse.select-options') }}"
                                data-store-url="{{ route('produse.quick-store') }}"
                                data-initial-product-id="{{ $currentProdusId }}"
                                data-initial-product-label="{{ $initialProdusLabel }}"
                                data-invalid="{{ $errors->has('produs_id') ? '1' : '0' }}"
                            ></div>
                        </div>
                        <div class="col-lg-6 mb-2 d-none" data-produs-mode="custom">
                            <label class="mb-0 ps-3">Denumire produs</label>
                            <input type="text" class="form-control bg-white rounded-3" name="custom_denumire" value="{{ $currentCustomDenumire }}" placeholder="Ex: Stickere personalizate">
                        </div>
                        <div class="col-lg-2 mb-2 d-none" data-produs-mode="custom">
                            <label class="mb-0 ps-3">Pret unitar</label>
                            <input type="number" min="0" step="0.01" class="form-control bg-white rounded-3" name="custom_pret_unitar" value="{{ $currentCustomPretUnitar }}">
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="mb-0 ps-3">Cantitate</label>
                            <input type="number" min="1" class="form-control bg-white rounded-3" name="cantitate" value="{{ $currentLinieCantitate }}">
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
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item comanda-accordion-item" id="fisiere">
                        <h2 class="accordion-header" id="heading-fisiere">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-fisiere" aria-expanded="false" aria-controls="collapse-fisiere">
                                <i class="fa-solid fa-paperclip me-2 text-secondary"></i>
                                <span>Fisiere</span>
                            </button>
                        </h2>
                        <div id="collapse-fisiere" class="accordion-collapse collapse" aria-labelledby="heading-fisiere">
                            <div class="accordion-body">
        <div class="row mb-4">
            <div class="col-lg-4 mb-3">
                <h6 class="mb-3 js-comanda-section" id="atasamente" data-collapse="#collapse-fisiere">Atasamente</h6>
                <form method="POST" action="{{ route('comenzi.atasamente.store', $comanda) }}" enctype="multipart/form-data" class="mb-3">
                    @csrf
                    <div class="input-group">
                        <input type="file" class="form-control" name="atasament[]" multiple required>
                        <button type="submit" class="btn btn-outline-primary">Incarca</button>
                    </div>
                </form>
                <ul class="list-group">
                    @forelse ($comanda->atasamente as $atasament)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="me-2">
                                <a href="{{ $atasament->fileUrl() }}" target="_blank" rel="noopener">{{ $atasament->original_name }}</a>
                                <div class="small text-muted">{{ number_format($atasament->size / 1024, 1) }} KB</div>
                            </div>
                            <div class="d-flex gap-1">
                                <a class="btn btn-sm btn-primary" href="{{ $atasament->fileUrl() }}" target="_blank" rel="noopener" title="Vezi" aria-label="Vezi">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <a class="btn btn-sm btn-success" href="{{ $atasament->downloadUrl() }}" title="Download" aria-label="Download">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                                <form method="POST" action="{{ $atasament->destroyUrl() }}" onsubmit="return confirm('Stergi atasamentul?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Sterge" aria-label="Sterge">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Nu exista atasamente.</li>
                    @endforelse
                </ul>
            </div>
            <div class="col-lg-4 mb-3">
                <h6 class="mb-3 js-comanda-section" id="facturi" data-collapse="#collapse-fisiere">Facturi</h6>
                @if ($canManageFacturi)
                    <form method="POST" action="{{ route('comenzi.facturi.store', $comanda) }}" enctype="multipart/form-data" class="mb-3">
                        @csrf
                        <div class="input-group">
                            <input type="file" class="form-control" name="factura[]" multiple required>
                            <button type="submit" class="btn p-0 border-0 bg-transparent" title="Incarca facturi" aria-label="Incarca facturi">
                                <span class="badge bg-primary">
                                    <i class="fa-solid fa-upload me-1"></i>{{ $facturiCount }}
                                </span>
                            </button>
                        </div>
                    </form>
                    <ul class="list-group mb-3">
                        @forelse ($comanda->facturi as $factura)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="me-2">
                                    <a href="{{ $factura->fileUrl() }}" target="_blank" rel="noopener">{{ $factura->original_name }}</a>
                                    <div class="small text-muted">
                                        {{ number_format($factura->size / 1024, 1) }} KB
                                        @if ($factura->uploadedBy)
                                            - {{ $factura->uploadedBy->name }}
                                        @endif
                                        @if ($factura->created_at)
                                            - {{ $factura->created_at->format('d.m.Y H:i') }}
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a class="btn btn-sm btn-primary" href="{{ $factura->fileUrl() }}" target="_blank" rel="noopener" title="Vezi" aria-label="Vezi">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                    <a class="btn btn-sm btn-success" href="{{ $factura->downloadUrl() }}" title="Download" aria-label="Download">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    <form method="POST" action="{{ $factura->destroyUrl() }}" onsubmit="return confirm('Stergi factura?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Sterge" aria-label="Sterge">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">Nu exista facturi.</li>
                        @endforelse
                    </ul>
                    <button
                        type="button"
                        class="btn p-0 border-0 bg-transparent mb-2"
                        data-bs-toggle="modal"
                        data-bs-target="#factura-email-modal"
                        {{ $canSendFacturaEmail ? '' : 'disabled' }}
                        title="Trimite email factura"
                        aria-label="Trimite email factura"
                    >
                        <span class="badge bg-secondary">
                            <i class="fa-solid fa-paper-plane me-1"></i>{{ $facturaEmailsCount }}
                        </span>
                    </button>
                    @if (!$clientEmail)
                        <div class="text-muted small">Clientul nu are email setat.</div>
                    @endif
                @else
                    <div class="text-muted">Facturile pot fi gestionate doar de supervizori.</div>
                @endif
            </div>
            <div class="col-lg-4 mb-3">
                <h6 class="mb-3 js-comanda-section" id="mockupuri" data-collapse="#collapse-fisiere">Mockup-uri</h6>
                <form method="POST" action="{{ route('comenzi.mockupuri.store', $comanda) }}" enctype="multipart/form-data" class="mb-3">
                    @csrf
                    <div class="mb-2">
                        <input type="file" class="form-control" name="mockup[]" multiple required>
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
                                <div class="me-2">
                                    <a href="{{ $mockup->fileUrl() }}" target="_blank" rel="noopener">{{ $mockup->original_name }}</a>
                                    <div class="small text-muted">{{ number_format($mockup->size / 1024, 1) }} KB</div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a class="btn btn-sm btn-primary" href="{{ $mockup->fileUrl() }}" target="_blank" rel="noopener" title="Vezi" aria-label="Vezi">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                    <a class="btn btn-sm btn-success" href="{{ $mockup->downloadUrl() }}" title="Download" aria-label="Download">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    <form method="POST" action="{{ $mockup->destroyUrl() }}" onsubmit="return confirm('Stergi mockup-ul?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Sterge" aria-label="Sterge">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
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

        @if ($canManageFacturi)
            <div class="modal fade" id="factura-email-modal" tabindex="-1" aria-labelledby="factura-email-label" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                        <h5 class="modal-title" id="factura-email-label">Trimite factura pe e-mail</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Inchide"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="small text-muted">Trimite către:</div>
                                <div class="fw-semibold">{{ $clientEmail ?: 'Email lipsă' }}</div>
                            </div>

                            <form method="POST" action="{{ route('comenzi.facturi.trimite-email', $comanda) }}">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label mb-1">Subiect</label>
                                    <input type="text" name="subject" class="form-control" value="{{ $defaultFacturaSubject }}" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label mb-1">Mesaj</label>
                                    <textarea name="body" class="form-control" rows="5" required>{{ $defaultFacturaBody }}</textarea>
                                    <div class="small text-muted mt-1">Mesajul poate fi modificat înainte de trimitere.</div>
                                </div>
                                <div class="mb-3">
                                    <div class="small text-muted">Facturi atașate:</div>
                                    @if ($facturiCount)
                                        <ul class="small mb-0">
                                            @foreach ($comanda->facturi as $factura)
                                                <li>{{ $factura->original_name }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <div class="text-muted small">Nu există facturi încărcate.</div>
                                    @endif
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary text-white" {{ $canSendFacturaEmail ? '' : 'disabled' }}>
                                        <i class="fa-solid fa-paper-plane me-1"></i> Trimite
                                    </button>
                                </div>
                            </form>

                            <hr class="my-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-semibold">E-mailuri trimise</div>
                                <span class="badge bg-secondary">{{ $facturaEmailsCount }}</span>
                            </div>
                            @forelse ($comanda->facturaEmails as $email)
                                @php
                                    $facturiSnapshot = collect($email->facturi ?? []);
                                    $facturiLabels = $facturiSnapshot->pluck('original_name')->filter()->implode(', ');
                                @endphp
                                <div class="border rounded-3 p-2 mb-2">
                                    <div class="small text-muted">
                                        {{ optional($email->created_at)->format('d.m.Y H:i') }}
                                        - {{ $email->recipient }}
                                        @if ($email->sentBy)
                                            - {{ $email->sentBy->name }}
                                        @endif
                                    </div>
                                    <div class="fw-semibold">{{ $email->subject }}</div>
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($email->body, 160) }}</div>
                                    <div class="small text-muted">Facturi: {{ $facturiLabels ?: '-' }}</div>
                                </div>
                            @empty
                                <div class="text-muted small">Nu s-au trimis e-mailuri.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item js-comanda-section comanda-accordion-item" id="plati" data-collapse="#collapse-plati">
                        <h2 class="accordion-header" id="heading-plati">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-plati" aria-expanded="false" aria-controls="collapse-plati">
                                <i class="fa-solid fa-credit-card me-2 text-success"></i>
                                <span>Plati</span>
                            </button>
                        </h2>
                        <div id="collapse-plati" class="accordion-collapse collapse" aria-labelledby="heading-plati">
                            <div class="accordion-body">
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="table-responsive rounded mb-3">
                    <table class="table table-sm table-bordered align-middle table-hover">
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
                    </div>

                    <div class="accordion-item js-comanda-section comanda-accordion-item" id="etape" data-collapse="#collapse-etape">
                        <h2 class="accordion-header" id="heading-etape">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-etape" aria-expanded="false" aria-controls="collapse-etape">
                                <i class="fa-solid fa-layer-group me-2 text-warning"></i>
                                <span>Etape comanda</span>
                            </button>
                        </h2>
                        <div id="collapse-etape" class="accordion-collapse collapse" aria-labelledby="heading-etape">
                            <div class="accordion-body">
                                <div class="row mb-4">
                                    <div class="col-lg-12">
                                        <div class="p-3 rounded-3 bg-light">
                                            <h6 class="mb-3">Asignari pe etape</h6>
                                            @if ($etape->isEmpty())
                                                <div class="text-muted">Nu exista etape configurate.</div>
                                            @else
                                                @foreach ($etape as $etapa)
                                                    <div class="mb-3">
                                                        <div class="fw-semibold mb-2">{{ $etapa->label }}</div>
                                                        <input type="hidden" name="etape[{{ $etapa->id }}][]" value="" form="comanda-update-form">
                                                        @if ($activeUsers->isEmpty())
                                                            <div class="text-muted">Nu exista utilizatori activi.</div>
                                                        @else
                                                            <div class="row g-2">
                                                                @foreach ($activeUsers as $user)
                                                                    <div class="col-lg-4 col-md-6">
                                                                        <div class="form-check">
                                                                            <input
                                                                                class="form-check-input"
                                                                                type="checkbox"
                                                                                name="etape[{{ $etapa->id }}][]"
                                                                                id="etapa-{{ $etapa->id }}-user-{{ $user->id }}"
                                                                                value="{{ $user->id }}"
                                                                                form="comanda-update-form"
                                                                                {{ $canEditAssignments ? '' : 'disabled' }}
                                                                                {{ in_array((string) $user->id, $assignedUserIdsByEtapa[$etapa->id] ?? [], true) ? 'checked' : '' }}
                                                                            >
                                                                            <label class="form-check-label" for="etapa-{{ $etapa->id }}-user-{{ $user->id }}">{{ $user->name }}</label>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-12 text-end">
                                        <button type="submit" class="btn btn-primary text-white rounded-3" form="comanda-update-form" {{ $canEditAssignments ? '' : 'disabled' }}>
                                            <i class="fa-solid fa-save me-1"></i> Salveaza asignarile
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .js-comanda-section {
                scroll-margin-top: 1rem;
            }
            .comanda-shell {
                border-radius: 32px;
                border: 1px solid rgba(112, 59, 59, 0.15);
                background-color: #ffffff;
                box-shadow: 0 18px 40px rgba(20, 24, 80, 0.08);
                overflow: hidden;
            }
            .comanda-header {
                background: linear-gradient(120deg, #703b3b, #141850);
                border-bottom: none;
                color: #ffffff;
            }
            .comanda-header .badge {
                box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
            }
            .comanda-nav .list-group-item {
                border: 0;
                border-radius: 12px;
                margin-bottom: 6px;
                transition: background-color 0.2s ease, color 0.2s ease;
            }
            .comanda-nav .list-group-item:hover {
                background-color: rgba(112, 59, 59, 0.08);
                color: #703b3b;
            }
            .comanda-nav .list-group-item.active {
                background-color: rgba(112, 59, 59, 0.12);
                color: #703b3b;
                font-weight: 600;
            }
            .comanda-accordion-item {
                border: 1px solid rgba(112, 59, 59, 0.12);
                border-radius: 18px;
                overflow: hidden;
                box-shadow: 0 12px 26px rgba(20, 24, 80, 0.05);
            }
            .comanda-accordion-item + .comanda-accordion-item {
                margin-top: 1rem;
            }
            .comanda-shell .accordion-button {
                background-color: #f8f9fa;
            }
            .comanda-shell .accordion-button:not(.collapsed) {
                background: linear-gradient(135deg, #fdf6f3, #f3f6ff);
                color: #141850;
            }
            .comanda-shell .form-control[readonly],
            .comanda-shell textarea[readonly],
            .comanda-shell .form-control:disabled,
            .comanda-shell .form-select:disabled {
                background-color: #f7f3f1;
                border-style: dashed;
                color: #6c757d;
            }
        </style>

        <script>
            window.addEventListener('DOMContentLoaded', () => {
                const sectionSelect = document.getElementById('comanda-section-select');
                const sectionEls = Array.from(document.querySelectorAll('.js-comanda-section'));
                const navLinks = new Map();
                const sectionActionButtons = document.querySelectorAll('[data-comanda-action]');
                const accordionEl = document.getElementById('comanda-accordion');
                const hasBootstrapCollapse = () => Boolean(window.bootstrap && window.bootstrap.Collapse);
                const produsModeInputs = document.querySelectorAll('input[name="produs_tip"]');
                const produsModeContainers = document.querySelectorAll('[data-produs-mode]');

                const getScrollTopOffset = () => 12;

                const scrollToSection = (sectionEl, smooth = true) => {
                    if (!sectionEl) return;
                    const y = window.scrollY + sectionEl.getBoundingClientRect().top - getScrollTopOffset();
                    window.scrollTo({ top: Math.max(0, y), behavior: smooth ? 'smooth' : 'auto' });
                };

                const openCollapseFor = (sectionEl) => {
                    if (!sectionEl) return Promise.resolve();
                    if (!hasBootstrapCollapse()) return Promise.resolve();
                    const collapseSelector = sectionEl.getAttribute('data-collapse');
                    if (!collapseSelector) return Promise.resolve();
                    const collapseEl = document.querySelector(collapseSelector);
                    if (!collapseEl) return Promise.resolve();

                    if (collapseEl.classList.contains('show')) return Promise.resolve();

                    return new Promise((resolve) => {
                        collapseEl.addEventListener('shown.bs.collapse', () => resolve(), { once: true });
                        window.bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                    });
                };

                const setActiveSection = (sectionId) => {
                    if (!sectionId) return;

                    navLinks.forEach((link, id) => {
                        link.classList.toggle('active', id === sectionId);
                    });

                    if (sectionSelect && sectionSelect.querySelector(`option[value="#${sectionId}"]`)) {
                        sectionSelect.value = `#${sectionId}`;
                    }
                };

                const isSectionVisible = (sectionEl) => {
                    const collapseParent = sectionEl.closest('.accordion-collapse');
                    if (collapseParent && !collapseParent.classList.contains('show')) {
                        return false;
                    }
                    return true;
                };

                const updateActiveSection = () => {
                    if (!sectionEls.length) return;

                    const offset = getScrollTopOffset() + 24;
                    let currentId = sectionEls[0].id;

                    sectionEls.forEach((sectionEl) => {
                        if (!isSectionVisible(sectionEl)) return;
                        const rect = sectionEl.getBoundingClientRect();
                        if (rect.top - offset <= 0) {
                            currentId = sectionEl.id;
                        }
                    });

                    setActiveSection(currentId);
                };

                const toggleAllSections = (expand) => {
                    if (!accordionEl || !hasBootstrapCollapse()) return;
                    accordionEl.querySelectorAll('.accordion-collapse').forEach((collapseEl) => {
                        const instance = window.bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                        if (expand) {
                            instance.show();
                        } else {
                            instance.hide();
                        }
                    });
                };

                const goToHash = async (hash, { smooth = true } = {}) => {
                    if (!hash || hash === '#') return;
                    const sectionEl = document.querySelector(hash);
                    if (!sectionEl) return;

                    await openCollapseFor(sectionEl);
                    scrollToSection(sectionEl, smooth);
                    setActiveSection(sectionEl.id);
                };

                document.querySelectorAll('[data-comanda-jump]').forEach((link) => {
                    const href = link.getAttribute('href');
                    if (href && href.startsWith('#')) {
                        navLinks.set(href.slice(1), link);
                    }

                    link.addEventListener('click', async (e) => {
                        const href = link.getAttribute('href');
                        if (!href || !href.startsWith('#')) return;
                        if (!hasBootstrapCollapse()) return;

                        e.preventDefault();
                        history.pushState(null, '', href);
                        await goToHash(href, { smooth: true });
                    });
                });

                sectionActionButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const action = button.getAttribute('data-comanda-action');
                        if (action === 'expand') {
                            toggleAllSections(true);
                        }
                        if (action === 'collapse') {
                            toggleAllSections(false);
                        }
                    });
                });

                if (sectionSelect) {
                    sectionSelect.addEventListener('change', async () => {
                        const value = sectionSelect.value;
                        if (!value) return;

                        if (!hasBootstrapCollapse()) {
                            window.location.hash = value;
                            return;
                        }

                        history.pushState(null, '', value);
                        await goToHash(value, { smooth: true });
                    });
                }

                window.addEventListener('hashchange', () => {
                    goToHash(window.location.hash, { smooth: false });
                });

                let scrollTick = false;
                window.addEventListener('scroll', () => {
                    if (scrollTick) return;
                    scrollTick = true;
                    window.requestAnimationFrame(() => {
                        updateActiveSection();
                        scrollTick = false;
                    });
                }, { passive: true });

                goToHash(window.location.hash, { smooth: false });
                updateActiveSection();

                const focusFirstInput = (container) => {
                    const input = container.querySelector('input:not([type="hidden"]), select, textarea');
                    if (input && !input.disabled) {
                        input.focus({ preventScroll: true });
                    }
                };

                const updateProdusMode = (shouldFocus = false) => {
                    const selected = document.querySelector('input[name="produs_tip"]:checked');
                    const mode = selected ? selected.value : 'existing';

                    produsModeContainers.forEach((container) => {
                        const isActive = container.dataset.produsMode === mode;
                        container.classList.toggle('d-none', !isActive);
                        container.querySelectorAll('input, select, textarea').forEach((input) => {
                            input.disabled = !isActive;
                        });

                        if (isActive && shouldFocus) {
                            focusFirstInput(container);
                        }
                    });
                };

                if (produsModeInputs.length) {
                    produsModeInputs.forEach((input) => {
                        input.addEventListener('change', () => updateProdusMode(true));
                    });

                    updateProdusMode();
                }
            });
        </script>
    </div>
</div>
@endsection
