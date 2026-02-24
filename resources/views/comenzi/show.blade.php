@extends ('layouts.app')

@section('content')
@php
    $statusPlataOptions = \App\Enums\StatusPlata::options();
    $access = $access ?? [];
    $canWriteComenzi = (bool) ($access['canWriteComenzi'] ?? false);
    $canWriteProduse = (bool) ($access['canWriteProduse'] ?? false);
    $canViewNecesarPrices = (bool) ($access['canViewNecesarPrices'] ?? false);
    $canWriteAtasamente = (bool) ($access['canWriteAtasamente'] ?? false);
    $canWriteMockupuri = (bool) ($access['canWriteMockupuri'] ?? false);
    $canWritePlatiCreate = (bool) ($access['canWritePlatiCreate'] ?? false);
    $canWritePlatiEditExisting = (bool) ($access['canWritePlatiEditExisting'] ?? false);
    $canEditAssignments = (bool) ($access['canEditAssignments'] ?? false);
    $canManageSolicitari = (bool) ($access['canManageSolicitari'] ?? false);
    $canEditNotaFrontdesk = (bool) ($access['canEditNotaFrontdesk'] ?? false);
    $canEditNotaGrafician = (bool) ($access['canEditNotaGrafician'] ?? false);
    $canEditNotaExecutant = (bool) ($access['canEditNotaExecutant'] ?? false);
    $canBypassDailyEditLock = (bool) ($access['canBypassDailyEditLock'] ?? false);
    $produseCount = $comanda->produse->count();
    $platiCount = $comanda->plati->count();
    $atasamenteCount = $comanda->atasamente->count();
    $facturiCount = $comanda->facturi->count();
    $mockupCount = $comanda->mockupuri->count();
    $mockupTypes = \App\Models\Mockup::typeOptions();
    $mockupGroups = $comanda->mockupuri->groupBy(fn ($item) => $item->tip ?: \App\Models\Mockup::TIP_INFO_MOCKUP);
    $mockupCountsByType = collect($mockupTypes)
        ->map(fn ($label, $type) => ($mockupGroups->get($type) ?? collect())->count())
        ->all();
    $latestMockupsByType = collect($mockupTypes)
        ->mapWithKeys(fn ($label, $type) => [$type => ($mockupGroups->get($type) ?? collect())->first()])
        ->all();
    $balance = (float) $comanda->total - (float) $comanda->total_platit;
    $balanceIsSettled = abs($balance) < 0.01;
    $balanceIsCredit = $balance < 0 && ! $balanceIsSettled;
    $balanceLabel = $balanceIsSettled ? 'Achitat' : ($balanceIsCredit ? 'Credit' : 'Rest de plata');
    $balanceValue = $balanceIsSettled ? 0 : abs($balance);
    $balanceAccent = ($balanceIsSettled || $balanceIsCredit) ? 'accent-forest' : 'accent-amber';
    $clientTelefon = optional($comanda->client)->telefon;
    $clientTelefonLink = $clientTelefon ? preg_replace('/[^0-9+]/', '', $clientTelefon) : '';
    $clientEmail = optional($comanda->client)->email;
    $canViewFacturi = (bool) ($access['canViewFacturi'] ?? false);
    $canManageFacturi = (bool) ($access['canManageFacturi'] ?? false);
    $canOperateFacturaFiles = (bool) ($access['canOperateFacturaFiles'] ?? false);
    $clientName = trim(optional($comanda->client)->nume_complet ?? '');
    $isCerereOferta = (bool) ($access['isCerereOferta'] ?? ($comanda->tip === \App\Enums\TipComanda::CerereOferta->value));
    $canEditMockupTiparFlags = (bool) ($access['canEditMockupTiparFlags'] ?? false);
    $canDownloadInternalDocs = (bool) ($access['canDownloadInternalDocs'] ?? false);
    $canDownloadOfertaPdf = (bool) ($access['canDownloadOfertaPdf'] ?? false);
    $canEditEtapeRestricted = $canEditAssignments;
    $canSaveEtapeAssignments = $canEditAssignments && (!$isCerereOferta || $etape->contains(fn ($item) => $item->slug === 'preluare_comanda'));
    $isGdprPhysicalSource = $comanda->sursa === \App\Enums\SursaComanda::Fizic->value;
    $gdprContactEmail = config('mail.reply_to.address') ?? config('mail.from.address');
    $gdprContactEmailLabel = $gdprContactEmail ?: 'adresa oficiala de e-mail a companiei';
    $gdprConsent = $comanda->gdprConsents->first();
    $gdprSignedAt = $gdprConsent?->signed_at ?? $gdprConsent?->created_at;
    $gdprSignedLabel = $gdprSignedAt ? $gdprSignedAt->format('d.m.Y H:i') : null;
    $gdprHasConsent = (bool) $gdprConsent;
    $gdprMethod = $gdprConsent?->method;
    $gdprMarketing = $gdprConsent?->consent_marketing ?? false;
    $gdprMediaMarketing = $gdprConsent?->consent_media_marketing ?? false;
    $gdprSignatureData = null;
    if ($gdprConsent?->signature_path) {
        $gdprSignaturePath = \Illuminate\Support\Facades\Storage::disk('public')->path($gdprConsent->signature_path);
        if (is_file($gdprSignaturePath)) {
            $gdprSignatureBinary = file_get_contents($gdprSignaturePath);
            if ($gdprSignatureBinary !== false) {
                $gdprSignatureData = 'data:image/png;base64,' . base64_encode($gdprSignatureBinary);
            }
        }
    }
    $currentClientId = old('client_id', $comanda->client_id);
    $initialClientLabel = '';
    if ((string) $currentClientId === (string) $comanda->client_id && $comanda->client) {
        $initialClientLabel = $comanda->client->nume_complet;
    }
    $currentStatus = old('status', $comanda->status);
    $currentTimp = old('timp_estimat_livrare', optional($comanda->timp_estimat_livrare)->format('Y-m-d\\TH:i'));
    $currentValabilitateOferta = old('valabilitate_oferta', optional($comanda->valabilitate_oferta)->format('Y-m-d'));
    $currentTip = old('tip', $comanda->tip);
    $currentSursa = old('sursa', $comanda->sursa);
    $isWebsiteOrder = $currentSursa === \App\Enums\SursaComanda::Website->value;
    $currentTipar = old('necesita_tipar_exemplu', $comanda->necesita_tipar_exemplu);
    $currentMockup = old('necesita_mockup', $comanda->necesita_mockup);
    $solicitariCount = $comanda->solicitari->count();
    $noteCount = $comanda->note->count();
    $noteGroups = $comanda->note->groupBy('role');
    $notesFrontdesk = $noteGroups->get('frontdesk', collect());
    $notesGrafician = $noteGroups->get('grafician', collect());
    $notesExecutant = $noteGroups->get('executant', collect());
    $noteCountFrontdesk = $notesFrontdesk->count();
    $noteCountGrafician = $notesGrafician->count();
    $noteCountExecutant = $notesExecutant->count();
    $currentAwb = old('awb', $comanda->awb);
    $currentProdusTip = old('produs_tip', 'existing');
    $currentProdusId = old('produs_id');
    $currentDescriere = old('descriere');
    $currentCustomDescriere = old('custom_descriere');
    $currentCustomDenumire = old('custom_denumire');
    $currentCustomNomenclatorId = old('custom_nomenclator_id');
    $currentCustomPretUnitar = old('custom_pret_unitar');
    $currentLinieCantitate = old('cantitate', 1);
    $initialProdusLabel = '';
    if ($currentProdusId) {
        $matchedProdus = $produse->first(fn ($produs) => (string) $produs->id === (string) $currentProdusId);
        if ($matchedProdus) {
            $initialProdusLabel = $matchedProdus->denumire . ' (' . number_format($matchedProdus->pret, 2) . ')';
        }
    }
    $billingAddress = $comanda->adresa_facturare ?? optional($comanda->client)->adresa;
    $shippingAddress = $comanda->adresa_livrare ?? $billingAddress;

    $comandaSections = [
        ['id' => 'detalii', 'label' => 'Detalii comanda'],
        ['id' => 'informatii-comanda', 'label' => 'Informatii comanda', 'count' => $solicitariCount],
        ['id' => 'note', 'label' => 'Note', 'count' => $noteCount],
        ['id' => 'necesar', 'label' => 'Necesar', 'count' => $produseCount],
        ['id' => 'atasamente', 'label' => 'Fisiere', 'count' => $atasamenteCount],
        ['id' => 'facturi', 'label' => 'Facturi', 'count' => $facturiCount],
        ['id' => 'mockupuri', 'label' => 'Info grafica', 'count' => $mockupCount],
        ['id' => 'plati', 'label' => 'Plati', 'count' => $platiCount],
        ['id' => 'etape', 'label' => 'Etape comanda'],
    ];
@endphp
<div class="mx-3 px-3 card comanda-shell">
    <div data-comanda-header>
        @include('comenzi.partials.header', [
            'comanda' => $comanda,
            'canWriteComenzi' => $canWriteComenzi,
            'statusuri' => $statusuri,
        ])
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
                                        <span class="badge bg-white text-dark border rounded-pill" data-section-count="{{ $section['id'] }}">{{ $section['count'] }}</span>
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
                            <option
                                value="#{{ $section['id'] }}"
                                data-section-option="{{ $section['id'] }}"
                                data-section-label="{{ $section['label'] }}"
                                data-section-has-count="{{ array_key_exists('count', $section) ? '1' : '0' }}"
                            >
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
                                <form id="comanda-update-form" method="POST" action="{{ route('comenzi.update', $comanda) }}" data-ajax-form data-ajax-scope="detalii">
            @csrf
            @method('PUT')
            <fieldset {{ $canWriteComenzi ? '' : 'disabled' }}>
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
                        <div class="mb-1"><strong>Telefon secundar:</strong> {{ optional($comanda->client)->telefon_secundar ?? '-' }}</div>
                        <div class="mb-1"><strong>Email:</strong> {{ optional($comanda->client)->email ?? '-' }}</div>
                        <div class="mb-1"><strong>Adresa client:</strong> {{ optional($comanda->client)->adresa ?? '-' }}</div>
                        <div class="mb-1"><strong>Adresa facturare:</strong> {{ $billingAddress ?? '-' }}</div>
                        <div><strong>Adresa livrare:</strong> {{ $shippingAddress ?? '-' }}</div>
                    </div>
                </div>
                <div class="col-lg-8 mb-3">
                    <div class="row">
                        <div class="col-lg-4 mb-3">
                            <label for="tip" class="mb-0 ps-3">Tip</label>
                            <select class="form-select bg-white rounded-3 {{ $errors->has('tip') ? 'is-invalid' : '' }}" name="tip" id="tip">
                                @foreach ($tipuri as $key => $label)
                                    <option value="{{ $key }}" {{ $currentTip === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label for="sursa" class="mb-0 ps-3">Sursa</label>
                            <select class="form-select bg-white rounded-3 {{ $errors->has('sursa') ? 'is-invalid' : '' }}" name="sursa" id="sursa">
                                @foreach ($surse as $key => $label)
                                    <option value="{{ $key }}" {{ $currentSursa === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
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
                            <label for="data_solicitarii" class="mb-0 ps-3">Data solicitarii</label>
                            <input
                                type="date"
                                class="form-control bg-white rounded-3 {{ $errors->has('data_solicitarii') ? 'is-invalid' : '' }}"
                                name="data_solicitarii"
                                id="data_solicitarii"
                                value="{{ old('data_solicitarii', optional($comanda->data_solicitarii)->format('Y-m-d')) }}"
                                required>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label for="valabilitate_oferta" class="mb-0 ps-3">Valabilitate oferta</label>
                            <input
                                type="date"
                                class="form-control bg-white rounded-3 {{ $errors->has('valabilitate_oferta') ? 'is-invalid' : '' }}"
                                name="valabilitate_oferta"
                                id="valabilitate_oferta"
                                value="{{ $currentValabilitateOferta }}"
                            >
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
                                <input class="form-check-input" type="checkbox" name="necesita_mockup" id="necesita_mockup" value="1"
                                    {{ $currentMockup ? 'checked' : '' }} {{ $canEditMockupTiparFlags ? '' : 'disabled' }}>
                                <label class="form-check-label" for="necesita_mockup">Necesita mockup</label>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-3 d-flex align-items-center">
                            <div class="form-check mt-4 ps-4">
                                <input class="form-check-input" type="checkbox" name="necesita_tipar_exemplu" id="necesita_tipar_exemplu" value="1"
                                    {{ $currentTipar ? 'checked' : '' }} {{ $canEditMockupTiparFlags ? '' : 'disabled' }}>
                                <label class="form-check-label" for="necesita_tipar_exemplu">Necesita tipar exemplu</label>
                            </div>
                        </div>                        
                        <div class="col-lg-12">
                            <div class="p-3 rounded-3 bg-light">
                                <h6 class="mb-2">AWB</h6>
                                @if ($isWebsiteOrder)
                                    <div class="row">
                                        <div class="col-lg-4">
                                            <label for="awb" class="mb-0 ps-3">AWB</label>
                                            <input
                                                type="text"
                                                class="form-control bg-white rounded-3 {{ $errors->has('awb') ? 'is-invalid' : '' }}"
                                                name="awb"
                                                id="awb"
                                                value="{{ $currentAwb }}">
                                        </div>
                                    </div>
                                @else
                                    <div class="text-muted small ps-3">Comanda nu este din website.</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-12 d-flex justify-content-lg-end justify-content-center mt-2">
                            @if ($canWriteComenzi)
                                <button type="submit" class="btn btn-primary text-white rounded-3">
                                    <i class="fa-solid fa-save me-1"></i> Salveaza modificarile
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            </fieldset>
                                </form>
                                <div class="small mt-2 d-none text-center" data-ajax-message="detalii"></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-lg-6">
                                        <div class="p-3 rounded-3 bg-light h-100">
                                            <div class="fw-semibold">Documente PDF</div>
                                            <div class="d-flex flex-wrap gap-2 mt-2">
                                                @if ($canDownloadOfertaPdf)
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('comenzi.pdf.oferta', $comanda) }}">
                                                        <i class="fa-solid fa-file-pdf me-1"></i> Descarca oferta
                                                    </a>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-primary" disabled>
                                                        <i class="fa-solid fa-file-pdf me-1"></i> Descarca oferta
                                                    </button>
                                                @endif
                                                @if ($canDownloadInternalDocs)
                                                    <a class="btn btn-sm btn-outline-dark" href="{{ route('comenzi.pdf.fisa-interna', $comanda) }}">
                                                        <i class="fa-solid fa-clipboard-list me-1"></i> Descarca fisa interna
                                                    </a>
                                                    <a class="btn btn-sm btn-outline-dark" href="{{ route('comenzi.pdf.proces-verbal', $comanda) }}">
                                                        <i class="fa-solid fa-clipboard-check me-1"></i> Proces verbal predare
                                                    </a>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-outline-dark" disabled>
                                                        <i class="fa-solid fa-clipboard-list me-1"></i> Descarca fisa interna
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-dark" disabled>
                                                        <i class="fa-solid fa-clipboard-check me-1"></i> Proces verbal predare
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="p-3 rounded-3 bg-light h-100">
                                            <div class="fw-semibold">Acord GDPR</div>
                                            <div data-gdpr-status>
                                                @include('comenzi.partials.gdpr-status', [
                                                    'canWriteComenzi' => $canWriteComenzi,
                                                    'gdprHasConsent' => $gdprHasConsent,
                                                    'comanda' => $comanda,
                                                    'gdprSignedLabel' => $gdprSignedLabel,
                                                    'gdprMethod' => $gdprMethod,
                                                    'gdprMarketing' => $gdprMarketing,
                                                    'gdprMediaMarketing' => $gdprMediaMarketing,
                                                    'isGdprPhysicalSource' => $isGdprPhysicalSource,
                                                    'clientEmail' => $clientEmail,
                                                ])
                                            </div>
                                            <div class="small mt-2 d-none" data-ajax-message="gdpr"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @php
                        $oldSolicitari = old('solicitari', [['solicitare_client' => '', 'cantitate' => '']]);
                        if (!is_array($oldSolicitari) || empty($oldSolicitari)) {
                            $oldSolicitari = [['solicitare_client' => '', 'cantitate' => '']];
                        }
                    @endphp
                    <div class="accordion-item js-comanda-section comanda-accordion-item" id="informatii-comanda" data-collapse="#collapse-informatii-comanda">
                        <h2 class="accordion-header" id="heading-informatii-comanda">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-informatii-comanda" aria-expanded="false" aria-controls="collapse-informatii-comanda">
                                <i class="fa-solid fa-clipboard-check me-2 text-info"></i>
                                <span>Informatii comanda</span>
                            </button>
                        </h2>
                        <div id="collapse-informatii-comanda" class="accordion-collapse collapse" aria-labelledby="heading-informatii-comanda">
                            <div class="accordion-body bg-soft-ice">
                                <div class="mb-4" data-solicitari-existing>
                                    @include('comenzi.partials.solicitari-existing', [
                                        'comanda' => $comanda,
                                        'canManageSolicitari' => $canManageSolicitari,
                                        'canBypassDailyEditLock' => $canBypassDailyEditLock,
                                    ])
                                </div>

                                <form method="POST" action="{{ route('comenzi.solicitari.store', $comanda) }}" data-solicitari-form data-ajax-form data-ajax-scope="solicitari" data-ajax-reset>
                                    @csrf
                                    <fieldset {{ $canManageSolicitari ? '' : 'disabled' }}>
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                            <div class="fw-semibold">Adauga solicitari noi</div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-solicitare-add>
                                                <i class="fa-solid fa-plus me-1"></i> Adauga solicitare
                                            </button>
                                        </div>
                                        <div data-solicitari-list>
                                            @foreach ($oldSolicitari as $index => $entry)
                                                <div class="row g-3 align-items-end mb-2" data-solicitare-row>
                                                    <div class="col-lg-8">
                                                        <label class="mb-0 ps-3">Solicitare client</label>
                                                        <textarea
                                                            class="form-control bg-white rounded-3"
                                                            name="solicitari[{{ $index }}][solicitare_client]"
                                                            rows="3"
                                                        >{{ $entry['solicitare_client'] ?? '' }}</textarea>
                                                    </div>
                                                    <div class="col-lg-3">
                                                        <label class="mb-0 ps-3">Cantitate</label>
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            class="form-control bg-white rounded-3"
                                                            name="solicitari[{{ $index }}][cantitate]"
                                                            value="{{ $entry['cantitate'] ?? '' }}"
                                                        >
                                                    </div>
                                                    <div class="col-lg-1 text-end">
                                                        <button type="button" class="btn btn-outline-danger btn-sm w-100" data-solicitare-remove>
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        @if ($canManageSolicitari)
                                            <div class="d-flex justify-content-end mt-2">
                                                <button type="submit" class="btn btn-sm btn-primary text-white">
                                                    <i class="fa-solid fa-save me-1"></i> Salveaza solicitari
                                                </button>
                                            </div>
                                        @endif
                                    </fieldset>
                                </form>
                                <div class="small mt-2 d-none" data-ajax-message="solicitari"></div>
                            </div>
                        </div>
                    </div>


                    @php
                        $oldNoteRole = old('note_role');
                    @endphp
                    <div class="accordion-item js-comanda-section comanda-accordion-item" id="note" data-collapse="#collapse-note">
                        <h2 class="accordion-header" id="heading-note">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-note" aria-expanded="false" aria-controls="collapse-note">
                                <i class="fa-solid fa-note-sticky me-2 text-warning"></i>
                                <span>Note</span>
                            </button>
                        </h2>
                        <div id="collapse-note" class="accordion-collapse collapse" aria-labelledby="heading-note">
                            <div class="accordion-body bg-soft-cream">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="p-3 rounded-3 bg-soft-sand">
                                            <div data-note-existing="frontdesk">
                                                @include('comenzi.partials.note-existing-role', [
                                                    'comanda' => $comanda,
                                                    'notes' => $notesFrontdesk,
                                                    'role' => 'frontdesk',
                                                    'canEditRole' => $canEditNotaFrontdesk,
                                                    'canBypassDailyEditLock' => $canBypassDailyEditLock,
                                                ])
                                            </div>

                                            @if ($canEditNotaFrontdesk)
                                                @php
                                                    $oldNotes = $oldNoteRole === 'frontdesk'
                                                        ? old('note_entries', [['nota' => '']])
                                                        : [['nota' => '']];
                                                @endphp
                                                <form method="POST" action="{{ route('comenzi.note.store', [$comanda, 'frontdesk']) }}" class="mt-3" data-note-form data-ajax-form data-ajax-scope="note" data-ajax-reset>
                                                    @csrf
                                                    <input type="hidden" name="note_role" value="frontdesk">
                                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                                        <div class="fw-semibold">Adauga note noi</div>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-note-add>
                                                            <i class="fa-solid fa-plus me-1"></i> Adauga nota
                                                        </button>
                                                    </div>
                                                    <div data-note-list>
                                                        @foreach ($oldNotes as $index => $entry)
                                                            <div class="row g-3 align-items-end mb-2" data-note-row>
                                                                <div class="col-lg-11">
                                                                    <label class="mb-0 ps-3">Nota</label>
                                                                    <textarea class="form-control bg-white rounded-3" name="note_entries[{{ $index }}][nota]" rows="3">{{ $entry['nota'] ?? '' }}</textarea>
                                                                </div>
                                                                <div class="col-lg-1 text-end">
                                                                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-note-remove>
                                                                        <i class="fa-solid fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <div class="d-flex justify-content-end mt-2">
                                                        <button type="submit" class="btn btn-sm btn-primary text-white">
                                                            <i class="fa-solid fa-save me-1"></i> Salveaza note
                                                        </button>
                                                    </div>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="p-3 rounded-3 bg-soft-sage">
                                            <div data-note-existing="grafician">
                                                @include('comenzi.partials.note-existing-role', [
                                                    'comanda' => $comanda,
                                                    'notes' => $notesGrafician,
                                                    'role' => 'grafician',
                                                    'canEditRole' => $canEditNotaGrafician,
                                                    'canBypassDailyEditLock' => $canBypassDailyEditLock,
                                                ])
                                            </div>

                                            @if ($canEditNotaGrafician)
                                                @php
                                                    $oldNotes = $oldNoteRole === 'grafician'
                                                        ? old('note_entries', [['nota' => '']])
                                                        : [['nota' => '']];
                                                @endphp
                                                <form method="POST" action="{{ route('comenzi.note.store', [$comanda, 'grafician']) }}" class="mt-3" data-note-form data-ajax-form data-ajax-scope="note" data-ajax-reset>
                                                    @csrf
                                                    <input type="hidden" name="note_role" value="grafician">
                                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                                        <div class="fw-semibold">Adauga note noi</div>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-note-add>
                                                            <i class="fa-solid fa-plus me-1"></i> Adauga nota
                                                        </button>
                                                    </div>
                                                    <div data-note-list>
                                                        @foreach ($oldNotes as $index => $entry)
                                                            <div class="row g-3 align-items-end mb-2" data-note-row>
                                                                <div class="col-lg-11">
                                                                    <label class="mb-0 ps-3">Nota</label>
                                                                    <textarea class="form-control bg-white rounded-3" name="note_entries[{{ $index }}][nota]" rows="3">{{ $entry['nota'] ?? '' }}</textarea>
                                                                </div>
                                                                <div class="col-lg-1 text-end">
                                                                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-note-remove>
                                                                        <i class="fa-solid fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <div class="d-flex justify-content-end mt-2">
                                                        <button type="submit" class="btn btn-sm btn-primary text-white">
                                                            <i class="fa-solid fa-save me-1"></i> Salveaza note
                                                        </button>
                                                    </div>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="p-3 rounded-3 bg-soft-sky">
                                            <div data-note-existing="executant">
                                                @include('comenzi.partials.note-existing-role', [
                                                    'comanda' => $comanda,
                                                    'notes' => $notesExecutant,
                                                    'role' => 'executant',
                                                    'canEditRole' => $canEditNotaExecutant,
                                                    'canBypassDailyEditLock' => $canBypassDailyEditLock,
                                                ])
                                            </div>

                                            @if ($canEditNotaExecutant)
                                                @php
                                                    $oldNotes = $oldNoteRole === 'executant'
                                                        ? old('note_entries', [['nota' => '']])
                                                        : [['nota' => '']];
                                                @endphp
                                                <form method="POST" action="{{ route('comenzi.note.store', [$comanda, 'executant']) }}" class="mt-3" data-note-form data-ajax-form data-ajax-scope="note" data-ajax-reset>
                                                    @csrf
                                                    <input type="hidden" name="note_role" value="executant">
                                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                                        <div class="fw-semibold">Adauga note noi</div>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-note-add>
                                                            <i class="fa-solid fa-plus me-1"></i> Adauga nota
                                                        </button>
                                                    </div>
                                                    <div data-note-list>
                                                        @foreach ($oldNotes as $index => $entry)
                                                            <div class="row g-3 align-items-end mb-2" data-note-row>
                                                                <div class="col-lg-11">
                                                                    <label class="mb-0 ps-3">Nota</label>
                                                                    <textarea class="form-control bg-white rounded-3" name="note_entries[{{ $index }}][nota]" rows="3">{{ $entry['nota'] ?? '' }}</textarea>
                                                                </div>
                                                                <div class="col-lg-1 text-end">
                                                                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-note-remove>
                                                                        <i class="fa-solid fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <div class="d-flex justify-content-end mt-2">
                                                        <button type="submit" class="btn btn-sm btn-primary text-white">
                                                            <i class="fa-solid fa-save me-1"></i> Salveaza note
                                                        </button>
                                                    </div>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="small mt-2 d-none" data-ajax-message="note"></div>
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
                                  <th>Descriere</th>
                                  <th width="15%">Cantitate</th>
                                  @if ($canViewNecesarPrices)
                                      <th width="15%">Pret unitar</th>
                                      <th width="15%">Total linie</th>
                                  @endif
                                  @if ($canWriteProduse)
                                      <th width="8%" class="text-end">Actiuni</th>
                                  @endif
                              </tr>
                          </thead>
                          <tbody data-necesar-table-body>
                              @include('comenzi.partials.necesar-table-body', [
                                  'comanda' => $comanda,
                                  'canWriteProduse' => $canWriteProduse,
                                  'canViewPreturi' => $canViewNecesarPrices,
                              ])
                          </tbody>
                      </table>
                  </div>
                  <div data-necesar-history class="mb-3">
                      @include('comenzi.partials.necesar-history', [
                          'histories' => $comanda->produsHistories,
                          'canViewPreturi' => $canViewNecesarPrices,
                      ])
                  </div>
                  <form method="POST" action="{{ route('comenzi.produse.store', $comanda) }}" data-necesar-form data-ajax-form data-ajax-scope="necesar" data-ajax-reset>
                    @csrf
                    <fieldset {{ $canWriteProduse ? '' : 'disabled' }}>
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
                        <div class="col-lg-4 mb-2" data-produs-mode="existing">
                            <label class="mb-0 ps-3">Produs</label>
                            <input type="hidden" name="update_product_description_default" value="0" data-existing-product-update-desc-flag>
                            <div
                                class="js-product-selector product-selector-inline"
                                data-name="produs_id"
                                data-search-url="{{ route('produse.select-options') }}"
                                data-store-url="{{ route('produse.quick-store') }}"
                                data-initial-product-id="{{ $currentProdusId }}"
                                data-initial-product-label="{{ $initialProdusLabel }}"
                                data-invalid="{{ $errors->has('produs_id') ? '1' : '0' }}"
                            ></div>
                        </div>
                        <div class="col-lg-4 mb-2" data-produs-mode="existing">
                            <label class="mb-0 ps-3">Descriere</label>
                            <textarea
                                class="form-control bg-white rounded-3 {{ $errors->has('descriere') ? 'is-invalid' : '' }}"
                                name="descriere"
                                data-existing-product-description
                                rows="2"
                                placeholder="Ex: model 2026"
                            >{{ $currentDescriere }}</textarea>
                            @if ($errors->has('descriere'))
                                <div class="invalid-feedback d-block">
                                    {{ $errors->first('descriere') }}
                                </div>
                            @endif
                        </div>
                        <div class="col-lg-6 mb-2 d-none" data-produs-mode="custom">
                            <label class="mb-0 ps-3">Denumire produs</label>
                            <div data-custom-product-selector data-search-url="{{ route('produse-custom.select-options') }}">
                                <input type="hidden" name="custom_nomenclator_id" value="{{ $currentCustomNomenclatorId }}" data-custom-product-id>
                                <input type="hidden" name="custom_add_to_nomenclator" value="0" data-custom-product-add-flag>
                                <input type="hidden" name="update_custom_description_default" value="0" data-custom-product-update-desc-flag>
                                <input
                                    type="text"
                                    class="form-control bg-white rounded-3 {{ $errors->has('custom_denumire') ? 'is-invalid' : '' }}"
                                    name="custom_denumire"
                                    value="{{ $currentCustomDenumire }}"
                                    placeholder="Ex: Agenda A5 folio 32mm"
                                    autocomplete="off"
                                    data-custom-product-query
                                >
                                <div class="list-group w-100 shadow-sm mt-1 d-none" style="max-height: 240px; overflow: auto;" data-custom-product-results></div>
                                @if ($errors->has('custom_denumire'))
                                    <div class="invalid-feedback d-block">
                                        {{ $errors->first('custom_denumire') }}
                                    </div>
                                @endif
                            </div>
                            <div class="form-text">Sugestii doar din nomenclatorul de produse custom.</div>
                            <div class="mt-2">
                                <label class="mb-0 ps-3">Descriere</label>
                                <textarea
                                    class="form-control bg-white rounded-3 {{ $errors->has('custom_descriere') ? 'is-invalid' : '' }}"
                                    name="custom_descriere"
                                    data-custom-product-description
                                    rows="3"
                                    placeholder="Ex: model 2026"
                                >{{ $currentCustomDescriere }}</textarea>
                                @if ($errors->has('custom_descriere'))
                                    <div class="invalid-feedback d-block">
                                        {{ $errors->first('custom_descriere') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if ($canViewNecesarPrices)
                            <div class="col-lg-2 mb-2 d-none" data-produs-mode="custom">
                                <label class="mb-0 ps-3">Pret unitar</label>
                                <input type="number" min="0" step="0.01" class="form-control bg-white rounded-3" name="custom_pret_unitar" value="{{ $currentCustomPretUnitar }}">
                            </div>
                        @endif
                        <div class="col-lg-2 mb-2">
                            <label class="mb-0 ps-3">Cantitate</label>
                            <input type="number" min="1" class="form-control bg-white rounded-3" name="cantitate" value="{{ $currentLinieCantitate }}">
                        </div>
                          <div class="col-lg-2 mb-2 text-end">
                              @if ($canWriteProduse)
                                  <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                      <i class="fa-solid fa-plus me-1"></i> Adauga
                                  </button>
                              @endif
                          </div>
                      </div>
                    </fieldset>
                  </form>
                  <div class="small mt-2 d-none" data-ajax-message="necesar"></div>
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
        <div data-fisiere-content>
            @include('comenzi.partials.fisiere-content', [
                'comanda' => $comanda,
                'canWriteAtasamente' => $canWriteAtasamente,
                'canViewFacturi' => $canViewFacturi,
                'canManageFacturi' => $canManageFacturi,
                'canOperateFacturaFiles' => $canOperateFacturaFiles,
                'canWriteMockupuri' => $canWriteMockupuri,
                'canBypassDailyEditLock' => $canBypassDailyEditLock,
                'mockupTypes' => $mockupTypes,
                'clientEmail' => $clientEmail,
            ])
        </div>
        <div class="small mt-2 d-none" data-ajax-message="fisiere"></div>

        

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
        <div class="row mb-4 {{ $isCerereOferta ? 'plati-disabled' : '' }}">
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
                                  <th class="text-end">Actiuni</th>
                              </tr>
                          </thead>
                          <tbody data-plati-table-body>
                              @include('comenzi.partials.plati-table-body', [
                                  'comanda' => $comanda,
                                  'metodePlata' => $metodePlata,
                                  'canWritePlatiCreate' => $canWritePlatiCreate,
                                  'canWritePlatiEditExisting' => $canWritePlatiEditExisting,
                              ])
                          </tbody>
                      </table>
                  </div>
                  <div data-plati-summary>
                      @include('comenzi.partials.plati-summary', ['comanda' => $comanda, 'statusPlataOptions' => $statusPlataOptions])
                  </div>
                  <form method="POST" action="{{ route('comenzi.plati.store', $comanda) }}" data-ajax-form data-ajax-scope="plati">
                    @csrf
                    <fieldset {{ $canWritePlatiCreate ? '' : 'disabled' }}>
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
                              @if ($canWritePlatiCreate)
                                  <button type="submit" class="btn btn-sm btn-outline-primary">
                                      <i class="fa-solid fa-plus me-1"></i> Adauga plata
                                  </button>
                              @endif
                          </div>
                      </div>
                    </fieldset>
                  </form>
                  <div class="small mt-2 d-none" data-ajax-message="plati"></div>
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
                                                    @php
                                                        $canEditCurrentEtapa = $canEditEtapeRestricted && (!$isCerereOferta || $etapa->slug === 'preluare_comanda');
                                                    @endphp
                                                    <div class="mb-3">
                                                        <div class="fw-semibold mb-2">{{ $etapa->label }}</div>
                                                        @if ($canEditCurrentEtapa)
                                                            <input type="hidden" name="etape[{{ $etapa->id }}][]" value="" form="comanda-update-form">
                                                        @endif
                                                        @if ($activeUsersByRole->isEmpty())
                                                            <div class="text-muted">Nu exista utilizatori activi.</div>
                                                        @else
                                                            <div class="row g-2">
                                                                @foreach ($activeUsersByRole as $roleGroup)
                                                                    <div class="col-lg-3 col-md-6">
                                                                        <div class="border rounded-3 bg-white p-2 h-100">
                                                                            <div class="small fw-semibold text-muted mb-2">{{ $roleGroup['name'] }}</div>
                                                                            @foreach ($roleGroup['users'] as $user)
                                                                                @php
                                                                                    $assignmentStatus = $assignmentStatusesByEtapaUser[$etapa->id][(string) $user->id] ?? null;
                                                                                @endphp
                                                                                <div class="form-check mb-1">
                                                                                    <input
                                                                                        class="form-check-input"
                                                                                        type="checkbox"
                                                                                        name="etape[{{ $etapa->id }}][]"
                                                                                        id="etapa-{{ $etapa->id }}-role-{{ $roleGroup['slug'] }}-user-{{ $user->id }}"
                                                                                        value="{{ $user->id }}"
                                                                                        form="comanda-update-form"
                                                                                        {{ $canEditCurrentEtapa ? '' : 'disabled' }}
                                                                                        {{ in_array((string) $user->id, $assignedUserIdsByEtapa[$etapa->id] ?? [], true) ? 'checked' : '' }}
                                                                                    >
                                                                                    <label class="form-check-label" for="etapa-{{ $etapa->id }}-role-{{ $roleGroup['slug'] }}-user-{{ $user->id }}">
                                                                                        {{ $user->name }}
                                                                                        @if ($assignmentStatus)
                                                                                            <span class="badge ms-1 {{ $assignmentStatus === 'pending' ? 'bg-warning text-dark' : 'bg-success' }}">
                                                                                                {{ $assignmentStatus === 'pending' ? 'in asteptare' : 'aprobat' }}
                                                                                            </span>
                                                                                        @endif
                                                                                    </label>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif

                                            <div class="mt-4" data-etape-history>
                                                @include('comenzi.partials.etape-history', [
                                                    'etapeHistories' => $comanda->etapaHistories,
                                                    'etape' => $etape,
                                                ])
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-12 text-end">
                                        @if ($canSaveEtapeAssignments)
                                            <button type="submit" class="btn btn-primary text-white rounded-3" form="comanda-update-form">
                                                <i class="fa-solid fa-save me-1"></i> Salveaza asignarile
                                            </button>
                                        @endif
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
            .bg-soft-ice {
                background-color: #e6f2ff;
            }
            .bg-soft-cream {
                background-color: #fff0e6;
            }
            .bg-soft-sand {
                background-color: #fff7d6;
            }
            .bg-soft-sage {
                background-color: #e6fff2;
            }
            .bg-soft-sky {
                background-color: #e8f0ff;
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
            .comanda-shell .plati-disabled {
                opacity: 0.65;
            }
            .comanda-shell .btn.is-loading {
                cursor: wait;
            }
            .comanda-shell .product-selector-inline .list-group,
            .comanda-shell [data-custom-product-selector] [data-custom-product-results] {
                position: static !important;
                z-index: auto;
            }
            .gdpr-modal-content {
                background-color: #ffffff;
            }
            .gdpr-modal-body {
                background-color: #ffffff;
            }
            .gdpr-card {
                background-color: #ffffff;
                border-radius: 20px;
                padding: 24px;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            }
            .gdpr-consent-title {
                font-size: 1.1rem;
                font-weight: 600;
            }
            .gdpr-signature-pad {
                border: 2px dashed #cbd5e1;
                border-radius: 16px;
                background-color: #ffffff;
                height: 320px;
            }
            .gdpr-canvas {
                width: 100%;
                height: 100%;
                display: block;
                touch-action: none;
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
                const solicitariForm = document.querySelector('[data-solicitari-form]');
                const necesarForm = document.querySelector('[data-necesar-form]');
                const trackedUnsavedFormSelector = 'form[data-ajax-form], form[data-sync-submit][data-unsaved-track="1"]';
                const trackedInitialStates = new WeakMap();
                const dirtyForms = new Set();
                let lastDirtyForm = null;
                let bypassUnsavedGuard = false;
                let pendingNavigationUrl = null;
                const allowUnsavedModalClose = new WeakSet();

                const getScrollTopOffset = () => 12;

                const isTrackedUnsavedForm = (form) => Boolean(form && form.matches(trackedUnsavedFormSelector));
                const serializeTrackedForm = (form) => {
                    if (!form) return '';
                    const formData = new FormData(form);
                    const entries = [];
                    for (const [key, value] of formData.entries()) {
                        if (value instanceof File) {
                            entries.push(`${key}=[file:${value.name}:${value.size}]`);
                        } else {
                            entries.push(`${key}=${String(value)}`);
                        }
                    }

                    return entries.join('&');
                };
                const ensureTrackedFormState = (form) => {
                    if (!isTrackedUnsavedForm(form)) {
                        return false;
                    }

                    if (!trackedInitialStates.has(form)) {
                        trackedInitialStates.set(form, serializeTrackedForm(form));
                    }

                    return true;
                };
                const pruneDetachedDirtyForms = () => {
                    dirtyForms.forEach((form) => {
                        if (!document.body.contains(form)) {
                            dirtyForms.delete(form);
                        }
                    });
                };
                const hasUnsavedChanges = () => {
                    pruneDetachedDirtyForms();
                    return dirtyForms.size > 0;
                };
                const evaluateDirtyForm = (form) => {
                    if (!ensureTrackedFormState(form)) {
                        return false;
                    }

                    const initialState = trackedInitialStates.get(form) ?? '';
                    const currentState = serializeTrackedForm(form);
                    const isDirty = currentState !== initialState;
                    if (isDirty) {
                        dirtyForms.add(form);
                        lastDirtyForm = form;
                    } else {
                        dirtyForms.delete(form);
                        if (lastDirtyForm === form) {
                            lastDirtyForm = null;
                        }
                    }

                    return isDirty;
                };
                const markFormSaved = (form) => {
                    if (!isTrackedUnsavedForm(form)) {
                        return;
                    }

                    trackedInitialStates.set(form, serializeTrackedForm(form));
                    dirtyForms.delete(form);
                    if (lastDirtyForm === form) {
                        lastDirtyForm = null;
                    }
                };
                const keyCandidatesFromErrorKey = (key) => {
                    if (!key) {
                        return [];
                    }

                    const dotKey = String(key);
                    const parts = dotKey.split('.');
                    const bracketKey = parts.reduce((result, part, index) => {
                        if (index === 0) {
                            return part;
                        }

                        return `${result}[${part}]`;
                    }, '');

                    return Array.from(new Set([dotKey, bracketKey]));
                };
                const findFieldsByName = (form, name) => Array.from(form.elements || [])
                    .filter((field) => field && typeof field.name === 'string' && field.name === name);
                const resolveErrorAnchor = (field) => {
                    if (!field) {
                        return null;
                    }

                    if ((field.getAttribute('type') || '').toLowerCase() === 'hidden') {
                        return field.closest('.js-client-selector, .js-product-selector, [data-custom-product-selector]') || field;
                    }

                    return field;
                };
                const clearInlineFieldErrors = (form) => {
                    if (!form) {
                        return;
                    }

                    Array.from(form.elements || []).forEach((field) => {
                        if (typeof field.classList?.remove === 'function') {
                            field.classList.remove('is-invalid');
                        }
                    });
                    document.querySelectorAll('[data-ajax-inline-error="1"]').forEach((el) => {
                        el.remove();
                    });
                };
                const applyInlineFieldErrors = (form, errors) => {
                    if (!form || !errors || typeof errors !== 'object') {
                        return false;
                    }

                    let applied = false;
                    Object.entries(errors).forEach(([key, messages]) => {
                        const message = Array.isArray(messages) ? messages[0] : messages;
                        if (!message) {
                            return;
                        }

                        const fields = keyCandidatesFromErrorKey(key)
                            .flatMap((candidate) => findFieldsByName(form, candidate));
                        const uniqueFields = Array.from(new Set(fields));
                        if (!uniqueFields.length) {
                            return;
                        }

                        uniqueFields.forEach((field, index) => {
                            if (typeof field.classList?.add === 'function') {
                                field.classList.add('is-invalid');
                            }

                            if (index > 0) {
                                return;
                            }

                            const anchor = resolveErrorAnchor(field);
                            if (!anchor) {
                                return;
                            }

                            const feedback = document.createElement('div');
                            feedback.className = 'invalid-feedback d-block';
                            feedback.dataset.ajaxInlineError = '1';
                            feedback.textContent = String(message);
                            anchor.insertAdjacentElement('afterend', feedback);
                        });

                        applied = true;
                    });

                    return applied;
                };

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

                document.querySelectorAll(trackedUnsavedFormSelector).forEach((form) => {
                    ensureTrackedFormState(form);
                });

                document.addEventListener('input', (event) => {
                    const form = event.target.closest('form');
                    if (!form) return;
                    evaluateDirtyForm(form);
                }, true);

                document.addEventListener('change', (event) => {
                    const form = event.target.closest('form');
                    if (!form) return;
                    evaluateDirtyForm(form);
                }, true);

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

                let prepareExistingProductDescriptionIntent = async () => true;
                let prepareCustomProductNomenclatorIntent = async () => true;
                let resetNecesarFormAfterAjax = () => {};
                const confirmWithModal = (options) => window.AppConfirm.confirm(options);
                let attemptNavigationWithUnsavedPrompt = async () => {};

                if (necesarForm) {
                    const selectedProductInput = necesarForm.querySelector('input[type="hidden"][name="produs_id"]');
                    const existingDescriptionInput = necesarForm.querySelector('textarea[name="descriere"]');
                    const customDescriptionInput = necesarForm.querySelector('textarea[name="custom_descriere"]');
                    const existingDescriptionUpdateFlagInput = necesarForm.querySelector('[data-existing-product-update-desc-flag]');
                    const customDescriptionUpdateFlagInput = necesarForm.querySelector('[data-custom-product-update-desc-flag]');
                    const normalizeDescription = (value) => (value || '').trim();
                    if (existingDescriptionInput) {
                        existingDescriptionInput.dataset.defaultDescription = existingDescriptionInput.value || '';
                    }
                    if (customDescriptionInput) {
                        customDescriptionInput.dataset.defaultDescription = customDescriptionInput.value || '';
                    }

                    resetNecesarFormAfterAjax = () => {
                        const existingModeInput = necesarForm.querySelector('#produs-tip-existing');
                        if (existingModeInput) {
                            existingModeInput.checked = true;
                        }

                        const productSearchInput = necesarForm.querySelector('.js-product-selector input[type="text"]');
                        if (productSearchInput) {
                            productSearchInput.value = '';
                            productSearchInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }

                        if (selectedProductInput) {
                            selectedProductInput.value = '';
                        }

                        if (existingDescriptionInput) {
                            existingDescriptionInput.value = '';
                            existingDescriptionInput.dataset.defaultDescription = '';
                        }

                        if (customDescriptionInput) {
                            customDescriptionInput.value = '';
                            customDescriptionInput.dataset.defaultDescription = '';
                        }

                        const customNameInput = necesarForm.querySelector('input[name="custom_denumire"]');
                        if (customNameInput) {
                            customNameInput.value = '';
                        }

                        const customPriceInput = necesarForm.querySelector('input[name="custom_pret_unitar"]');
                        if (customPriceInput) {
                            customPriceInput.value = '';
                        }

                        const customNomenclatorInput = necesarForm.querySelector('[data-custom-product-id]');
                        if (customNomenclatorInput) {
                            customNomenclatorInput.value = '';
                        }

                        const addToNomenclatorInput = necesarForm.querySelector('[data-custom-product-add-flag]');
                        if (addToNomenclatorInput) {
                            addToNomenclatorInput.value = '0';
                        }
                        if (existingDescriptionUpdateFlagInput) {
                            existingDescriptionUpdateFlagInput.value = '0';
                        }
                        if (customDescriptionUpdateFlagInput) {
                            customDescriptionUpdateFlagInput.value = '0';
                        }

                        const customResultsList = necesarForm.querySelector('[data-custom-product-results]');
                        if (customResultsList) {
                            customResultsList.classList.add('d-none');
                            customResultsList.innerHTML = '';
                        }

                        const quantityInput = necesarForm.querySelector('input[name="cantitate"]');
                        if (quantityInput) {
                            quantityInput.value = '1';
                        }

                        updateProdusMode();
                    };

                    const syncExistingDescriptionFromProduct = (product) => {
                        if (!existingDescriptionInput) {
                            return;
                        }

                        const defaultDescription = product?.descriere ?? '';
                        existingDescriptionInput.value = defaultDescription;
                        existingDescriptionInput.dataset.defaultDescription = defaultDescription;
                    };

                    document.addEventListener('product-selector:change', (event) => {
                        if (!necesarForm.contains(event.target)) {
                            return;
                        }

                        const detail = event.detail || {};
                        if (detail.name !== 'produs_id') {
                            return;
                        }

                        syncExistingDescriptionFromProduct(detail.product || null);
                    });

                    prepareExistingProductDescriptionIntent = async () => {
                        if (existingDescriptionUpdateFlagInput) {
                            existingDescriptionUpdateFlagInput.value = '0';
                        }

                        const selectedMode = necesarForm.querySelector('input[name="produs_tip"]:checked');
                        if (!selectedMode || selectedMode.value !== 'existing') {
                            return true;
                        }

                        const selectedProductId = (selectedProductInput?.value || '').trim();
                        if (selectedProductId === '' || !existingDescriptionInput) {
                            return true;
                        }

                        const typedDescription = normalizeDescription(existingDescriptionInput.value);
                        const defaultDescription = normalizeDescription(existingDescriptionInput.dataset.defaultDescription || '');
                        if (typedDescription === defaultDescription) {
                            return true;
                        }

                        const confirmed = await confirmWithModal({
                            title: 'Actualizare descriere produs',
                            message: 'Urmeaza sa adaugi un produs existent cu o descriere diferita. Doresti sa actualizezi descrierea?',
                            confirmText: 'Da',
                            cancelText: 'Nu',
                            confirmClass: 'btn-primary',
                        });
                        if (confirmed && existingDescriptionUpdateFlagInput) {
                            existingDescriptionUpdateFlagInput.value = '1';
                        }

                        return true;
                    };

                    const customSelectorRoot = necesarForm.querySelector('[data-custom-product-selector]');
                    if (customSelectorRoot) {
                        const searchUrl = customSelectorRoot.dataset.searchUrl;
                        const queryInput = customSelectorRoot.querySelector('[data-custom-product-query]');
                        const selectedIdInput = customSelectorRoot.querySelector('[data-custom-product-id]');
                        const addFlagInput = customSelectorRoot.querySelector('[data-custom-product-add-flag]');
                        const updateDescriptionFlagInput = customSelectorRoot.querySelector('[data-custom-product-update-desc-flag]');
                        const resultsList = customSelectorRoot.querySelector('[data-custom-product-results]');
                        let selectedCustomOption = null;

                        let customSearchTimer = null;
                        let customSearchNonce = 0;
                        let customOptions = [];

                        const normalizeText = (value) => (value || '').trim().toLowerCase();
                        const closeCustomResults = () => {
                            if (!resultsList) return;
                            resultsList.classList.add('d-none');
                            resultsList.innerHTML = '';
                        };

                        const renderCustomResults = (items) => {
                            if (!resultsList) return;
                            resultsList.innerHTML = '';
                            if (!items.length) {
                                const empty = document.createElement('div');
                                empty.className = 'list-group-item text-muted small';
                                empty.textContent = 'Nu exista produse in nomenclator.';
                                resultsList.appendChild(empty);
                                resultsList.classList.remove('d-none');
                                return;
                            }

                            items.forEach((item) => {
                                const option = document.createElement('button');
                                option.type = 'button';
                                option.className = 'list-group-item list-group-item-action';
                                option.textContent = item.label;
                                option.dataset.customOptionId = String(item.id);
                                option.dataset.customOptionLabel = item.label;
                                option.dataset.customOptionDescription = item.descriere || '';
                                resultsList.appendChild(option);
                            });
                            resultsList.classList.remove('d-none');
                        };

                        const setCustomSelection = (item) => {
                            if (!queryInput || !selectedIdInput) return;
                            queryInput.value = item.label || '';
                            selectedIdInput.value = item.id ? String(item.id) : '';
                            selectedCustomOption = {
                                id: item.id ? String(item.id) : '',
                                label: item.label || '',
                                descriere: item.descriere || '',
                            };
                            if (customDescriptionInput) {
                                customDescriptionInput.value = selectedCustomOption.descriere;
                                customDescriptionInput.dataset.defaultDescription = selectedCustomOption.descriere;
                            }
                            closeCustomResults();
                        };

                        const syncSelectionFromExactMatch = () => {
                            if (!queryInput || !selectedIdInput) return;
                            const exact = customOptions.find((item) => normalizeText(item.label) === normalizeText(queryInput.value));
                            if (exact) {
                                selectedIdInput.value = String(exact.id);
                                selectedCustomOption = {
                                    id: String(exact.id),
                                    label: exact.label || '',
                                    descriere: exact.descriere || '',
                                };
                            } else {
                                selectedIdInput.value = '';
                                selectedCustomOption = null;
                            }
                        };

                        const fetchCustomOptions = async (searchValue = '') => {
                            if (!searchUrl) return;
                            const nonce = ++customSearchNonce;
                            try {
                                const params = { search: searchValue, limit: 12 };
                                let payload = null;
                                if (window.axios) {
                                    const response = await window.axios.get(searchUrl, { params });
                                    payload = response?.data;
                                } else {
                                    const query = new URLSearchParams(params).toString();
                                    const response = await fetch(`${searchUrl}?${query}`, {
                                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                                    });
                                    payload = await response.json();
                                }

                                if (nonce !== customSearchNonce) return;
                                customOptions = Array.isArray(payload?.results) ? payload.results : [];
                                renderCustomResults(customOptions);
                                syncSelectionFromExactMatch();
                            } catch (error) {
                                if (nonce !== customSearchNonce) return;
                                customOptions = [];
                                renderCustomResults([]);
                                if (selectedIdInput) {
                                    selectedIdInput.value = '';
                                }
                            }
                        };

                        if (queryInput && selectedIdInput) {
                            queryInput.addEventListener('focus', () => {
                                fetchCustomOptions((queryInput.value || '').trim());
                            });

                            queryInput.addEventListener('input', () => {
                                selectedIdInput.value = '';
                                selectedCustomOption = null;
                                if (customDescriptionInput) {
                                    customDescriptionInput.dataset.defaultDescription = '';
                                }
                                if (customSearchTimer) {
                                    clearTimeout(customSearchTimer);
                                }
                                customSearchTimer = setTimeout(() => {
                                    fetchCustomOptions((queryInput.value || '').trim());
                                }, 220);
                            });
                        }

                        if (resultsList) {
                            resultsList.addEventListener('mousedown', (event) => {
                                const option = event.target.closest('[data-custom-option-id]');
                                if (!option) return;
                                event.preventDefault();
                                setCustomSelection({
                                    id: option.dataset.customOptionId || '',
                                    label: option.dataset.customOptionLabel || '',
                                    descriere: option.dataset.customOptionDescription || '',
                                });
                            });
                        }

                        document.addEventListener('click', (event) => {
                            if (!customSelectorRoot.contains(event.target)) {
                                closeCustomResults();
                            }
                        });

                        if (selectedIdInput.value && queryInput && normalizeText(queryInput.value) === '' && searchUrl) {
                            if (window.axios) {
                                window.axios.get(searchUrl, { params: { id: selectedIdInput.value } }).then((response) => {
                                    const first = response?.data?.results?.[0];
                                    if (first?.id && queryInput) {
                                        queryInput.value = first.label || '';
                                        selectedIdInput.value = String(first.id);
                                        selectedCustomOption = {
                                            id: String(first.id),
                                            label: first.label || '',
                                            descriere: first.descriere || '',
                                        };
                                        if (customDescriptionInput && !customDescriptionInput.value) {
                                            customDescriptionInput.value = selectedCustomOption.descriere;
                                        }
                                        if (customDescriptionInput) {
                                            customDescriptionInput.dataset.defaultDescription = selectedCustomOption.descriere;
                                        }
                                    }
                                }).catch(() => {
                                    selectedIdInput.value = '';
                                    selectedCustomOption = null;
                                });
                            }
                        }

                        prepareCustomProductNomenclatorIntent = async () => {
                            if (!addFlagInput) {
                                return true;
                            }

                            addFlagInput.value = '0';
                            if (updateDescriptionFlagInput) {
                                updateDescriptionFlagInput.value = '0';
                            }
                            const selectedMode = necesarForm.querySelector('input[name="produs_tip"]:checked');
                            if (!selectedMode || selectedMode.value !== 'custom') {
                                return true;
                            }

                            const typedName = (queryInput?.value || '').trim();
                            if (typedName === '') {
                                return true;
                            }

                            if (selectedIdInput && selectedIdInput.value) {
                                const typedDescription = normalizeDescription(customDescriptionInput?.value || '');
                                const defaultDescription = normalizeDescription(
                                    selectedCustomOption?.descriere
                                    ?? customDescriptionInput?.dataset.defaultDescription
                                    ?? ''
                                );

                                if (typedDescription !== defaultDescription) {
                                    const confirmedDescriptionUpdate = await confirmWithModal({
                                        title: 'Actualizare descriere produs custom',
                                        message: 'Urmeaza sa adaugi un produs existent cu o descriere diferita. Doresti sa actualizezi descrierea?',
                                        confirmText: 'Da',
                                        cancelText: 'Nu',
                                        confirmClass: 'btn-primary',
                                    });
                                    if (confirmedDescriptionUpdate && updateDescriptionFlagInput) {
                                        updateDescriptionFlagInput.value = '1';
                                    }
                                }

                                return true;
                            }

                            const confirmed = await confirmWithModal({
                                title: 'Produs nou',
                                message: 'Doresti sa adaugi produsul nou in nomenclator?',
                                confirmText: 'Da',
                                confirmClass: 'btn-primary',
                            });
                            addFlagInput.value = confirmed ? '1' : '0';
                            return true;
                        };
                    }
                }

                const messageTimers = new Map();
                const hideAjaxMessage = (scope) => {
                    if (!scope) return;
                    const els = Array.from(document.querySelectorAll(`[data-ajax-message="${scope}"]`));
                    if (!els.length) return;
                    els.forEach((el) => {
                        el.classList.add('d-none');
                        el.textContent = '';
                        el.classList.remove('text-success', 'text-danger', 'text-warning');
                    });
                    if (messageTimers.has(scope)) {
                        clearTimeout(messageTimers.get(scope));
                        messageTimers.delete(scope);
                    }
                };

                const showAjaxMessage = (scope, message, type = 'success') => {
                    if (!scope) return;
                    const els = Array.from(document.querySelectorAll(`[data-ajax-message="${scope}"]`));
                    if (!els.length) return;

                    els.forEach((el) => {
                        el.textContent = message;
                        el.classList.remove('d-none', 'text-success', 'text-danger', 'text-warning');
                        if (type === 'error') {
                            el.classList.add('text-danger');
                        } else if (type === 'warning') {
                            el.classList.add('text-warning');
                        } else {
                            el.classList.add('text-success');
                        }
                    });

                    if (messageTimers.has(scope)) {
                        clearTimeout(messageTimers.get(scope));
                    }
                    messageTimers.set(scope, setTimeout(() => {
                        els.forEach((el) => el.classList.add('d-none'));
                    }, 4000));
                };

                const updateSectionCount = (sectionId, count) => {
                    if (!sectionId) return;
                    const badge = document.querySelector(`[data-section-count="${sectionId}"]`);
                    if (badge) {
                        badge.textContent = String(count);
                    }
                    const option = document.querySelector(`option[data-section-option="${sectionId}"]`);
                    if (option && option.dataset.sectionHasCount === '1') {
                        const label = option.dataset.sectionLabel || option.textContent;
                        option.textContent = `${label} (${count})`;
                    }
                };

                const applyAjaxPayload = (payload) => {
                    if (!payload) return;
                    if (payload.header_html) {
                        const header = document.querySelector('[data-comanda-header]');
                        if (header) header.innerHTML = payload.header_html;
                    }
                    if (payload.solicitari_html) {
                        const wrap = document.querySelector('[data-solicitari-existing]');
                        if (wrap) wrap.innerHTML = payload.solicitari_html;
                    }
                    if (payload.notes_html) {
                        Object.entries(payload.notes_html).forEach(([role, html]) => {
                            const wrap = document.querySelector(`[data-note-existing="${role}"]`);
                            if (wrap) wrap.innerHTML = html;
                        });
                    }
                    if (payload.produse_html) {
                        const body = document.querySelector('[data-necesar-table-body]');
                        if (body) body.innerHTML = payload.produse_html;
                    }
                    if (payload.necesar_history_html) {
                        const history = document.querySelector('[data-necesar-history]');
                        if (history) history.innerHTML = payload.necesar_history_html;
                    }
                    if (payload.plati_html) {
                        const body = document.querySelector('[data-plati-table-body]');
                        if (body) body.innerHTML = payload.plati_html;
                    }
                    if (payload.plati_summary_html) {
                        const summary = document.querySelector('[data-plati-summary]');
                        if (summary) summary.innerHTML = payload.plati_summary_html;
                    }
                    if (payload.fisiere_html) {
                        const files = document.querySelector('[data-fisiere-content]');
                        if (files) files.innerHTML = payload.fisiere_html;
                    }
                    if (payload.gdpr_status_html) {
                        const gdprStatus = document.querySelector('[data-gdpr-status]');
                        if (gdprStatus) gdprStatus.innerHTML = payload.gdpr_status_html;
                    }
                    if (payload.etape_history_html) {
                        const etapaHistory = document.querySelector('[data-etape-history]');
                        if (etapaHistory) etapaHistory.innerHTML = payload.etape_history_html;
                    }
                    if (payload.counts) {
                        const countMap = {
                            necesar: 'necesar',
                            plati: 'plati',
                            solicitari: 'informatii-comanda',
                            note: 'note',
                            atasamente: 'atasamente',
                            facturi: 'facturi',
                            mockupuri: 'mockupuri',
                        };
                        Object.entries(countMap).forEach(([payloadKey, sectionId]) => {
                            if (Object.prototype.hasOwnProperty.call(payload.counts, payloadKey)) {
                                updateSectionCount(sectionId, payload.counts[payloadKey]);
                            }
                        });
                    }
                };

                const getSubmitButtons = (form, submitter = null) => {
                    const buttons = Array.from(form.querySelectorAll('button[type="submit"]'))
                        .filter((button) => !button.disabled);
                    if (
                        submitter
                        && submitter.tagName === 'BUTTON'
                        && submitter.type === 'submit'
                        && submitter.form === form
                        && !buttons.includes(submitter)
                    ) {
                        buttons.push(submitter);
                    }
                    return buttons;
                };

                const setFormLoadingState = (form, submitter = null, { disableControls = true } = {}) => {
                    const controlsToRestore = disableControls
                        ? Array.from(form.querySelectorAll('input, select, textarea, button'))
                            .filter((control) => {
                                const type = control.getAttribute('type') || '';
                                return type.toLowerCase() !== 'hidden' && !control.disabled;
                            })
                        : [];

                    const submitButtons = getSubmitButtons(form, submitter);
                    const buttonStates = submitButtons.map((button) => {
                        const originalHtml = button.innerHTML;
                        const originalMinWidth = button.style.minWidth || '';
                        const originallyDisabled = button.disabled;
                        const text = (button.textContent || '').replace(/\s+/g, ' ').trim();
                        const width = button.getBoundingClientRect().width;
                        const label = text ? `${text.replace(/\.+$/, '')}...` : '';

                        if (!button.style.minWidth && width > 0) {
                            button.style.minWidth = `${Math.ceil(width)}px`;
                        }
                        button.setAttribute('disabled', 'disabled');
                        button.setAttribute('aria-busy', 'true');
                        button.classList.add('is-loading');
                        if (label) {
                            button.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>${label}`;
                        } else {
                            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                        }

                        return {
                            button,
                            originalHtml,
                            originalMinWidth,
                            originallyDisabled,
                        };
                    });

                    if (disableControls) {
                        // Disable currently enabled controls to avoid duplicate edits during async submit.
                        controlsToRestore.forEach((control) => control.setAttribute('disabled', 'disabled'));
                    }

                    return { controlsToRestore, buttonStates };
                };

                const restoreFormLoadingState = (loadingState) => {
                    if (!loadingState) return;
                    const { controlsToRestore, buttonStates } = loadingState;

                    controlsToRestore.forEach((control) => control.removeAttribute('disabled'));
                    buttonStates.forEach((state) => {
                        const { button, originalHtml, originalMinWidth, originallyDisabled } = state;
                        button.innerHTML = originalHtml;
                        if (originalMinWidth) {
                            button.style.minWidth = originalMinWidth;
                        } else {
                            button.style.removeProperty('min-width');
                        }
                        button.classList.remove('is-loading');
                        button.removeAttribute('aria-busy');
                        if (originallyDisabled) {
                            button.setAttribute('disabled', 'disabled');
                        } else {
                            button.removeAttribute('disabled');
                        }
                    });
                };

                const submitAjaxForm = async (form, submitter = null) => {
                    const scope = form.dataset.ajaxScope || '';
                    const action = form.getAttribute('action');
                    if (!action) return;

                    hideAjaxMessage(scope);
                    clearInlineFieldErrors(form);

                    let loadingState = null;

                    try {
                        // Capture values before disabling controls (disabled fields are omitted from FormData).
                        const formData = new FormData(form);
                        loadingState = setFormLoadingState(form, submitter, { disableControls: true });
                        let payload = null;

                        if (window.axios) {
                            const response = await window.axios.post(action, formData, {
                                headers: { Accept: 'application/json' },
                            });
                            payload = response?.data;
                        } else {
                            const response = await fetch(action, {
                                method: 'POST',
                                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                                body: formData,
                            });
                            payload = await response.json();
                            if (!response.ok) {
                                throw { response: { data: payload } };
                            }
                        }

                        applyAjaxPayload(payload);
                        if (payload?.message) {
                            const messageType = payload?.message_type || 'success';
                            showAjaxMessage(scope, payload.message, messageType);
                        }
                        if (form.hasAttribute('data-ajax-reset')) {
                            form.reset();
                            if (form.matches('[data-necesar-form]')) {
                                resetNecesarFormAfterAjax();
                            }
                        }
                        markFormSaved(form);
                        if (pendingNavigationUrl && !hasUnsavedChanges()) {
                            const targetUrl = pendingNavigationUrl;
                            pendingNavigationUrl = null;
                            bypassUnsavedGuard = true;
                            window.location.href = targetUrl;
                            return;
                        }
                        if (scope === 'gdpr') {
                            const gdprModalEl = document.getElementById('gdpr-modal');
                            const gdprModalInstance = gdprModalEl && window.bootstrap && window.bootstrap.Modal
                                ? window.bootstrap.Modal.getInstance(gdprModalEl)
                                : null;
                            if (gdprModalInstance) {
                                gdprModalInstance.hide();
                            }
                        }
                    } catch (error) {
                        const data = error?.response?.data;
                        let message = data?.message || 'A aparut o eroare. Incearca din nou.';
                        const messageType = data?.message_type || 'error';
                        const hasInlineErrors = applyInlineFieldErrors(form, data?.errors);
                        if (data?.errors) {
                            const firstError = Object.values(data.errors).flat()[0];
                            if (firstError) {
                                message = firstError;
                            }
                        }
                        if (hasInlineErrors) {
                            showAjaxMessage(scope, message, 'error');
                        } else {
                            showAjaxMessage(scope, message, messageType);
                        }
                    } finally {
                        restoreFormLoadingState(loadingState);
                    }
                };

                attemptNavigationWithUnsavedPrompt = async (url) => {
                    if (!url || bypassUnsavedGuard || !hasUnsavedChanges()) {
                        bypassUnsavedGuard = true;
                        if (url) {
                            window.location.href = url;
                        }
                        return;
                    }

                    const shouldSave = await confirmWithModal({
                        title: 'Modificari nesalvate',
                        message: 'Doriti salvarea modificarilor facute?',
                        confirmText: 'Salveaza',
                        cancelText: 'Paraseste fara salvare',
                        confirmClass: 'btn-primary',
                    });

                    if (!shouldSave) {
                        bypassUnsavedGuard = true;
                        window.location.href = url;
                        return;
                    }

                    const connectedDirtyForms = Array.from(dirtyForms).filter((form) => document.body.contains(form));
                    const dirtyForm = (lastDirtyForm && connectedDirtyForms.includes(lastDirtyForm))
                        ? lastDirtyForm
                        : connectedDirtyForms[0];

                    if (!dirtyForm) {
                        return;
                    }

                    if (dirtyForm.matches('[data-ajax-form]')) {
                        pendingNavigationUrl = url;
                        await submitAjaxForm(dirtyForm);
                        return;
                    }

                    if (dirtyForm.matches('form[data-sync-submit]')) {
                        bypassUnsavedGuard = true;
                        pendingNavigationUrl = null;
                        if (typeof dirtyForm.requestSubmit === 'function') {
                            dirtyForm.requestSubmit();
                            return;
                        }

                        dirtyForm.submit();
                    }
                };

                document.addEventListener('submit', async (event) => {
                    const form = event.target;
                    if (!form || !form.matches('[data-ajax-form]')) {
                        return;
                    }
                    event.preventDefault();

                    const confirmMessage = form.dataset.confirm;
                    if (confirmMessage) {
                        const confirmed = await confirmWithModal({
                            title: 'Confirmare',
                            message: confirmMessage,
                            confirmText: 'Confirma',
                            confirmClass: 'btn-danger',
                        });
                        if (!confirmed) {
                            return;
                        }
                    }
                    if (form.matches('[data-necesar-form]')) {
                        const canContinueExistingProductFlow = await prepareExistingProductDescriptionIntent();
                        if (!canContinueExistingProductFlow) {
                            return;
                        }

                        const canContinueCustomProductFlow = await prepareCustomProductNomenclatorIntent();
                        if (!canContinueCustomProductFlow) {
                            return;
                        }
                    }
                    submitAjaxForm(form, event.submitter || null);
                });

                document.addEventListener('submit', (event) => {
                    const form = event.target;
                    if (!form || !form.matches('form[data-sync-submit]')) {
                        return;
                    }

                    bypassUnsavedGuard = true;
                    pendingNavigationUrl = null;
                    setFormLoadingState(form, event.submitter || null, { disableControls: false });
                });

                window.addEventListener('beforeunload', (event) => {
                    if (bypassUnsavedGuard || !hasUnsavedChanges()) {
                        return;
                    }

                    event.preventDefault();
                    event.returnValue = '';
                });

                document.addEventListener('click', async (event) => {
                    if (event.defaultPrevented) {
                        return;
                    }

                    const link = event.target.closest('a[href]');
                    if (!link) {
                        return;
                    }

                    if (link.dataset.unsavedIgnore === '1') {
                        return;
                    }

                    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                        return;
                    }

                    const rawHref = (link.getAttribute('href') || '').trim();
                    if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:')) {
                        return;
                    }

                    if (link.hasAttribute('download')) {
                        return;
                    }

                    if ((link.getAttribute('target') || '').toLowerCase() === '_blank') {
                        return;
                    }

                    const destination = link.href;
                    if (!destination || !hasUnsavedChanges()) {
                        return;
                    }

                    event.preventDefault();
                    await attemptNavigationWithUnsavedPrompt(destination);
                }, true);

                document.querySelectorAll('.modal[data-unsaved-modal="1"]').forEach((modalEl) => {
                    modalEl.addEventListener('hide.bs.modal', async (event) => {
                        if (allowUnsavedModalClose.has(modalEl) || bypassUnsavedGuard) {
                            allowUnsavedModalClose.delete(modalEl);
                            return;
                        }

                        const form = modalEl.querySelector('form[data-sync-submit][data-unsaved-track="1"]');
                        if (!form || !dirtyForms.has(form)) {
                            return;
                        }

                        event.preventDefault();
                        const discard = await confirmWithModal({
                            title: 'Modificari nesalvate',
                            message: 'Textul emailului nu a fost trimis. Inchizi fara trimitere?',
                            confirmText: 'Inchide fara trimitere',
                            cancelText: 'Continua editarea',
                            confirmClass: 'btn-danger',
                        });
                        if (!discard) {
                            return;
                        }

                        form.reset();
                        clearInlineFieldErrors(form);
                        markFormSaved(form);
                        const colorPreview = form.querySelector('[data-email-color]');
                        if (colorPreview) {
                            colorPreview.style.backgroundColor = '#6c757d';
                        }

                        allowUnsavedModalClose.add(modalEl);
                        if (window.bootstrap && window.bootstrap.Modal) {
                            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        }
                    });
                });

                if (solicitariForm) {
                    const list = solicitariForm.querySelector('[data-solicitari-list]');
                    const addButton = solicitariForm.querySelector('[data-solicitare-add]');

                    const buildRow = (index) => {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'row g-3 align-items-end mb-2';
                        wrapper.setAttribute('data-solicitare-row', '');
                        wrapper.innerHTML = `
                            <div class="col-lg-8">
                                <label class="mb-0 ps-3">Solicitare client</label>
                                <textarea class="form-control bg-white rounded-3" name="solicitari[${index}][solicitare_client]" rows="3"></textarea>
                            </div>
                            <div class="col-lg-3">
                                <label class="mb-0 ps-3">Cantitate</label>
                                <input type="number" min="1" class="form-control bg-white rounded-3" name="solicitari[${index}][cantitate]">
                            </div>
                            <div class="col-lg-1 text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm w-100" data-solicitare-remove>
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        `;
                        return wrapper;
                    };

                    const renumberRows = () => {
                        if (!list) return;
                        const rows = Array.from(list.querySelectorAll('[data-solicitare-row]'));
                        rows.forEach((row, index) => {
                            const textarea = row.querySelector('textarea');
                            const input = row.querySelector('input[type="number"]');
                            if (textarea) textarea.name = `solicitari[${index}][solicitare_client]`;
                            if (input) input.name = `solicitari[${index}][cantitate]`;
                        });
                    };

                    if (addButton && list) {
                        addButton.addEventListener('click', () => {
                            const nextIndex = list.querySelectorAll('[data-solicitare-row]').length;
                            list.appendChild(buildRow(nextIndex));
                            evaluateDirtyForm(solicitariForm);
                        });
                        list.addEventListener('click', (event) => {
                            const target = event.target.closest('[data-solicitare-remove]');
                            if (!target) return;
                            const row = target.closest('[data-solicitare-row]');
                            if (!row) return;
                            row.remove();
                            if (list.querySelectorAll('[data-solicitare-row]').length === 0) {
                                list.appendChild(buildRow(0));
                            }
                            renumberRows();
                            evaluateDirtyForm(solicitariForm);
                        });
                    }
                }

                const noteForms = document.querySelectorAll('[data-note-form]');
                if (noteForms.length) {
                    noteForms.forEach((form) => {
                        const list = form.querySelector('[data-note-list]');
                        const addButton = form.querySelector('[data-note-add]');
                        if (!list || !addButton) return;

                        const buildRow = (index) => {
                            const wrapper = document.createElement('div');
                            wrapper.className = 'row g-3 align-items-end mb-2';
                            wrapper.setAttribute('data-note-row', '');
                            wrapper.innerHTML = `
                                <div class="col-lg-11">
                                    <label class="mb-0 ps-3">Nota</label>
                                    <textarea class="form-control bg-white rounded-3" name="note_entries[${index}][nota]" rows="3"></textarea>
                                </div>
                                <div class="col-lg-1 text-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-note-remove>
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            `;
                            return wrapper;
                        };

                        const renumberRows = () => {
                            const rows = Array.from(list.querySelectorAll('[data-note-row]'));
                            rows.forEach((row, index) => {
                                const textarea = row.querySelector('textarea');
                                if (textarea) textarea.name = `note_entries[${index}][nota]`;
                            });
                        };

                        addButton.addEventListener('click', () => {
                            const nextIndex = list.querySelectorAll('[data-note-row]').length;
                            list.appendChild(buildRow(nextIndex));
                            evaluateDirtyForm(form);
                        });

                        list.addEventListener('click', (event) => {
                            const target = event.target.closest('[data-note-remove]');
                            if (!target) return;
                            const row = target.closest('[data-note-row]');
                            if (!row) return;
                            row.remove();
                            if (list.querySelectorAll('[data-note-row]').length === 0) {
                                list.appendChild(buildRow(0));
                            }
                            renumberRows();
                            evaluateDirtyForm(form);
                        });
                    });
                }

                const gdprModal = document.getElementById('gdpr-modal');
                if (gdprModal) {
                    const canvas = gdprModal.querySelector('[data-gdpr-canvas]');
                    const form = gdprModal.querySelector('[data-gdpr-form]');
                    const signatureInput = gdprModal.querySelector('[data-gdpr-signature]');
                    const clearButton = gdprModal.querySelector('[data-gdpr-clear]');
                    if (canvas && form && signatureInput) {
                        let ctx = null;
                        let drawing = false;
                        let hasSignature = false;
                        let canvasWidth = 0;
                        let canvasHeight = 0;
                        const signatureData = gdprModal.dataset.gdprSignatureData;

                        const setupCanvas = () => {
                            const rect = canvas.getBoundingClientRect();
                            const ratio = window.devicePixelRatio || 1;
                            canvasWidth = rect.width;
                            canvasHeight = rect.height;
                            canvas.width = Math.max(1, rect.width * ratio);
                            canvas.height = Math.max(1, rect.height * ratio);
                            ctx = canvas.getContext('2d');
                            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                            ctx.lineWidth = 2.5;
                            ctx.lineCap = 'round';
                            ctx.strokeStyle = '#111827';
                            clearCanvas();
                        };

                        const clearCanvas = () => {
                            if (!ctx) return;
                            ctx.clearRect(0, 0, canvasWidth, canvasHeight);
                            ctx.fillStyle = '#ffffff';
                            ctx.fillRect(0, 0, canvasWidth, canvasHeight);
                            hasSignature = false;
                        };

                        const drawSignatureImage = () => {
                            if (!signatureData || !ctx) return;
                            const image = new Image();
                            image.onload = () => {
                                const scale = Math.min(canvasWidth / image.width, canvasHeight / image.height, 1);
                                const drawWidth = image.width * scale;
                                const drawHeight = image.height * scale;
                                const offsetX = (canvasWidth - drawWidth) / 2;
                                const offsetY = (canvasHeight - drawHeight) / 2;
                                ctx.drawImage(image, offsetX, offsetY, drawWidth, drawHeight);
                                hasSignature = true;
                            };
                            image.src = signatureData;
                        };

                        const getPoint = (event) => {
                            const rect = canvas.getBoundingClientRect();
                            return {
                                x: event.clientX - rect.left,
                                y: event.clientY - rect.top,
                            };
                        };

                        const startDraw = (event) => {
                            drawing = true;
                            const point = getPoint(event);
                            ctx.beginPath();
                            ctx.moveTo(point.x, point.y);
                            if (event.pointerId !== undefined) {
                                canvas.setPointerCapture(event.pointerId);
                            }
                        };

                        const draw = (event) => {
                            if (!drawing) return;
                            const point = getPoint(event);
                            ctx.lineTo(point.x, point.y);
                            ctx.stroke();
                            hasSignature = true;
                        };

                        const endDraw = (event) => {
                            drawing = false;
                            if (event.pointerId !== undefined) {
                                canvas.releasePointerCapture(event.pointerId);
                            }
                        };

                        canvas.addEventListener('pointerdown', startDraw);
                        canvas.addEventListener('pointermove', draw);
                        canvas.addEventListener('pointerup', endDraw);
                        canvas.addEventListener('pointerleave', endDraw);

                        if (clearButton) {
                            clearButton.addEventListener('click', () => {
                                clearCanvas();
                            });
                        }

                        form.addEventListener('submit', (event) => {
                            if (!hasSignature) {
                                event.preventDefault();
                                alert('Semnatura este necesara pentru a salva acordul.');
                                return;
                            }
                            signatureInput.value = canvas.toDataURL('image/png');
                        });

                        gdprModal.addEventListener('shown.bs.modal', () => {
                            setupCanvas();
                            drawSignatureImage();
                        });
                    }
                }
            });
        </script>
    </div>
</div>

<div class="modal fade" id="gdpr-modal" tabindex="-1" aria-labelledby="gdpr-modal-label" aria-hidden="true" data-gdpr-signature-data="{{ $gdprSignatureData ?? '' }}">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content gdpr-modal-content">
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title" id="gdpr-modal-label">{{ $isGdprPhysicalSource ? 'Semneaza acordul GDPR' : 'Inregistreaza acordul GDPR' }}</h5>
                    @if ($isGdprPhysicalSource)
                        <div class="small text-muted">Clientul semneaza direct in chenarul de mai jos.</div>
                    @else
                        <div class="small text-muted">Pentru comenzile preluate la distanta, acordul este acceptat implicit pentru toate cele 3 consimtamanturi.</div>
                    @endif
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Inchide"></button>
            </div>
            <div class="modal-body gdpr-modal-body">
                <div class="gdpr-card">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-6">
                            <div class="small text-muted">Client</div>
                            <div class="fw-semibold">{{ $clientName ?: 'Client' }}</div>
                            <div class="small text-muted">Comanda #{{ $comanda->id }}</div>
                        </div>
                        <div class="col-lg-6 text-lg-end">
                            <div class="small text-muted">Operator</div>
                            <div class="fw-semibold">{{ $currentUser?->name ?? '-' }}</div>
                            <div class="small text-muted">Data: {{ now()->format('d.m.Y H:i') }}</div>
                        </div>
                    </div>
                    <hr class="my-4">
                    <form method="POST" action="{{ route('comenzi.gdpr.store', $comanda) }}" data-gdpr-form data-ajax-form data-ajax-scope="gdpr">
                        @csrf
                        <input type="hidden" name="method" value="{{ $isGdprPhysicalSource ? 'signature' : 'checkbox' }}">
                        @if ($isGdprPhysicalSource)
                            <input type="hidden" name="signature_data" data-gdpr-signature>

                            <div class="gdpr-consent-title mb-2">
                                Semneaza in chenar pentru acordul privind prelucrarea datelor cu caracter personal
                                si politica privind promovarea produselor si serviciilor in scop de marketing/promovare.
                            </div>

                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="gdpr-processing" name="consent_processing" value="1" required>
                                <label class="form-check-label" for="gdpr-processing">
                                    Sunt de acord cu prelucrarea datelor cu caracter personal pentru derularea comenzii.
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="gdpr-marketing" name="consent_marketing" value="1">
                                <label class="form-check-label" for="gdpr-marketing">
                                    Sunt de acord sa primesc informari despre produse si servicii (optional).
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="gdpr-media-marketing" name="consent_media_marketing" value="1">
                                <label class="form-check-label" for="gdpr-media-marketing">
                                    Sunt de acord ca fotografiile si filmele realizate asupra produselor sa fie utilizate in scop de marketing si promovare.
                                </label>
                            </div>

                            <div class="gdpr-signature-pad mt-4">
                                <canvas class="gdpr-canvas" data-gdpr-canvas></canvas>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                                <div class="small text-muted">Semnati folosind mouse-ul sau tableta grafica.</div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary" data-gdpr-clear>Curata</button>
                                    <button type="submit" class="btn btn-success text-white">Salveaza acordul</button>
                                </div>
                            </div>
                        @else
                            <input type="hidden" name="consent_processing" value="1">
                            <input type="hidden" name="consent_marketing" value="1">
                            <input type="hidden" name="consent_media_marketing" value="1">

                            <div class="gdpr-consent-title mb-2">
                                Pentru aceasta comanda, clientul accepta implicit:
                            </div>
                            <ul class="mb-3">
                                <li>prelucrarea datelor cu caracter personal pentru derularea comenzii;</li>
                                <li>primirea informarilor privind produse si servicii;</li>
                                <li>utilizarea foto/video a produselor in scop de marketing si promovare.</li>
                            </ul>

                            <div class="alert alert-info mb-0">
                                Daca se doreste modificarea acordului GDPR, solicitarea se transmite prin e-mail catre
                                @if ($gdprContactEmail)
                                    <a href="mailto:{{ $gdprContactEmail }}">{{ $gdprContactEmail }}</a>.
                                @else
                                    {{ $gdprContactEmailLabel }}.
                                @endif
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-success text-white">Salveaza acordul</button>
                            </div>
                        @endif
                    </form>
                    <div class="small mt-2 d-none" data-ajax-message="gdpr"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

