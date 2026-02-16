@extends ('layouts.app')

@section('content')
@php
    $statusPlataOptions = \App\Enums\StatusPlata::options();
    $currentUser = auth()->user();
    $canWriteComenzi = $currentUser?->hasPermission('comenzi.write') ?? false;
    $canWriteProduse = $currentUser?->hasPermission('comenzi.produse.write') ?? false;
    $canWriteAtasamente = $currentUser?->hasPermission('comenzi.atasamente.write') ?? false;
    $canWriteMockupuri = $currentUser?->hasPermission('comenzi.mockupuri.write') ?? false;
    $canWritePlati = $currentUser?->hasPermission('comenzi.plati.write') ?? false;
    $canWriteEtape = $currentUser?->hasPermission('comenzi.etape.write') ?? false;
    $canSendOfertaEmail = $currentUser?->hasPermission('comenzi.email.send') ?? false;
    $canSendFacturaEmail = $currentUser?->hasPermission('facturi.email.send') ?? false;
    $canEditAssignments = $comanda->canEditAssignments($currentUser) && $canWriteEtape;
    $canEditNotaFrontdesk = $comanda->canEditNotaFrontdesk($currentUser) && $canWriteComenzi;
    $canEditNotaGrafician = $comanda->canEditNotaGrafician($currentUser) && $canWriteComenzi;
    $canEditNotaExecutant = $comanda->canEditNotaExecutant($currentUser) && $canWriteComenzi;
    $canBypassDailyEditLock = $currentUser?->hasAnyRole(['supervizor', 'superadmin']) ?? false;
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
    $selectedMockupLinkTypes = collect(old('mockup_link_types', []))
        ->map(fn ($value) => (string) $value)
        ->filter(fn ($value) => array_key_exists($value, $mockupTypes))
        ->unique()
        ->values()
        ->all();
    $facturaEmailsCount = $comanda->facturaEmails->count();
    $ofertaEmailsCount = $comanda->ofertaEmails->count();
    $balance = (float) $comanda->total - (float) $comanda->total_platit;
    $balanceIsSettled = abs($balance) < 0.01;
    $balanceIsCredit = $balance < 0 && ! $balanceIsSettled;
    $balanceLabel = $balanceIsSettled ? 'Achitat' : ($balanceIsCredit ? 'Credit' : 'Rest de plata');
    $balanceValue = $balanceIsSettled ? 0 : abs($balance);
    $balanceAccent = ($balanceIsSettled || $balanceIsCredit) ? 'accent-forest' : 'accent-amber';
    $clientTelefon = optional($comanda->client)->telefon;
    $clientTelefonLink = $clientTelefon ? preg_replace('/[^0-9+]/', '', $clientTelefon) : '';
    $clientEmail = optional($comanda->client)->email;
    $canViewFacturi = $comanda->canViewFacturi($currentUser);
    $canManageFacturi = $comanda->canManageFacturi($currentUser);
    $clientName = trim(optional($comanda->client)->nume_complet ?? '');
    $appName = config('app.name');
    $subjectClientName = $clientName ?: 'Client';
    $isCerereOferta = $comanda->tip === \App\Enums\TipComanda::CerereOferta->value;
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
        ? "Rezumat comanda:\n" . $orderLines->implode("\n") . "\n\n"
        : '';
    $defaultFacturaSubject = "Factura {$appName} - {$subjectClientName} - comanda #{$comanda->id}";
    $defaultFacturaBody = "Buna ziua {$subjectClientName},\n\nPuteti descarca factura {$appName} pentru comanda #{$comanda->id} folosind butonul din email.\n\n{$orderSummary}Va multumim,\n{$appName}";
    $defaultOfertaSubject = "Oferta {$appName} - {$subjectClientName} - comanda #{$comanda->id}";
    $defaultOfertaBody = "Buna ziua {$subjectClientName},\n\nPuteti descarca oferta {$appName} pentru comanda #{$comanda->id} folosind butonul din email.\n\n{$orderSummary}Va multumim,\n{$appName}";
    $defaultGdprSubject = "GDPR {$appName} - {$subjectClientName} - comanda #{$comanda->id}";
    $defaultGdprBody = "Buna ziua {$subjectClientName},\n\nPuteti descarca acordul GDPR pentru comanda #{$comanda->id} folosind butonul din email.\n\nCu respect,\n{$appName}";
    $canSendFacturaEmailEnabled = $canSendFacturaEmail && $facturiCount > 0 && !empty($clientEmail);
    $canSendOfertaEmailEnabled = $canSendOfertaEmail && !empty($clientEmail);
    $gdprConsent = $comanda->gdprConsents->first();
    $gdprSignedAt = $gdprConsent?->signed_at ?? $gdprConsent?->created_at;
    $gdprSignedLabel = $gdprSignedAt ? $gdprSignedAt->format('d.m.Y H:i') : null;
    $gdprHasConsent = (bool) $gdprConsent;
    $gdprMarketing = $gdprConsent?->consent_marketing ?? false;
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
    $canSendGdprEmailEnabled = $canSendOfertaEmail && $gdprHasConsent && !empty($clientEmail);
    $currentClientId = old('client_id', $comanda->client_id);
    $initialClientLabel = '';
    if ((string) $currentClientId === (string) $comanda->client_id && $comanda->client) {
        $initialClientLabel = $comanda->client->nume_complet;
    }
    $currentStatus = old('status', $comanda->status);
    $currentTimp = old('timp_estimat_livrare', optional($comanda->timp_estimat_livrare)->format('Y-m-d\\TH:i'));
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
    $emailTemplatePayload = $emailTemplates->mapWithKeys(fn ($template) => [
        $template->id => [
            'subject' => $template->subject,
            'body' => $template->body_html,
            'color' => $template->color,
        ],
    ])->all();
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
                                    {{ $currentMockup ? 'checked' : '' }}>
                                <label class="form-check-label" for="necesita_mockup">Necesita mockup</label>
                            </div>
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
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-12 d-flex justify-content-center">
                    @if ($canWriteComenzi)
                        <button type="submit" class="btn btn-primary text-white rounded-3">
                            <i class="fa-solid fa-save me-1"></i> Salveaza modificarile
                        </button>
                    @endif
                </div>
            </div>
            </fieldset>
                                </form>
                                <div class="small mt-2 d-none text-center" data-ajax-message="detalii"></div>
                                <div class="row mb-2">
                                    <div class="col-lg-12">
                                        <div class="p-3 rounded-3 bg-light">
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                <div class="fw-semibold">Documente PDF</div>
                                            </div>
                                            <div class="d-flex flex-wrap gap-2 mt-2">
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('comenzi.email.show', $comanda) }}">
                                                    <i class="fa-solid fa-envelope me-1"></i> Emailuri
                                                </a>
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('comenzi.email.history', $comanda) }}">
                                                    <i class="fa-solid fa-envelope-open-text me-1"></i> Emailuri trimise
                                                </a>
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('comenzi.pdf.oferta', $comanda) }}">
                                                    <i class="fa-solid fa-file-pdf me-1"></i> Descarca oferta
                                                </a>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#oferta-email-modal"
                                                >
                                                    <i class="fa-solid fa-paper-plane me-1"></i> Trimite oferta pe e-mail
                                                </button>
                                                <a class="btn btn-sm btn-outline-dark" href="{{ route('comenzi.pdf.fisa-interna', $comanda) }}">
                                                    <i class="fa-solid fa-clipboard-list me-1"></i> Descarca fisa interna
                                                </a>
                                                <a class="btn btn-sm btn-outline-dark" href="{{ route('comenzi.pdf.proces-verbal', $comanda) }}">
                                                    <i class="fa-solid fa-clipboard-check me-1"></i> Proces verbal predare
                                                </a>
                                            </div>
                                            <div data-gdpr-status>
                                                @include('comenzi.partials.gdpr-status', [
                                                    'canWriteComenzi' => $canWriteComenzi,
                                                    'gdprHasConsent' => $gdprHasConsent,
                                                    'comanda' => $comanda,
                                                    'canSendGdprEmailEnabled' => $canSendGdprEmailEnabled,
                                                    'gdprSignedLabel' => $gdprSignedLabel,
                                                    'gdprMarketing' => $gdprMarketing,
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
                                        'canEditNotaFrontdesk' => $canEditNotaFrontdesk,
                                        'canBypassDailyEditLock' => $canBypassDailyEditLock,
                                    ])
                                </div>

                                <form method="POST" action="{{ route('comenzi.solicitari.store', $comanda) }}" data-solicitari-form data-ajax-form data-ajax-scope="solicitari" data-ajax-reset>
                                    @csrf
                                    <fieldset {{ $canEditNotaFrontdesk ? '' : 'disabled' }}>
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
                                        @if ($canEditNotaFrontdesk)
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
                                  <th width="15%">Cantitate</th>
                                  <th width="15%">Pret unitar</th>
                                  <th width="15%">Total linie</th>
                                  <th width="8%" class="text-end">Actiuni</th>
                              </tr>
                          </thead>
                          <tbody data-necesar-table-body>
                              @include('comenzi.partials.necesar-table-body', ['comanda' => $comanda, 'canWriteProduse' => $canWriteProduse])
                          </tbody>
                      </table>
                  </div>
                  <form method="POST" action="{{ route('comenzi.produse.store', $comanda) }}" data-necesar-form data-ajax-form data-ajax-scope="necesar">
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
                        <div class="col-lg-8 mb-2" data-produs-mode="existing">
                            <label class="mb-0 ps-3">Produs</label>
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
                        <div class="col-lg-6 mb-2 d-none" data-produs-mode="custom">
                            <label class="mb-0 ps-3">Denumire produs</label>
                            <div class="position-relative" data-custom-product-selector data-search-url="{{ route('produse-custom.select-options') }}">
                                <input type="hidden" name="custom_nomenclator_id" value="{{ $currentCustomNomenclatorId }}" data-custom-product-id>
                                <input type="hidden" name="custom_add_to_nomenclator" value="0" data-custom-product-add-flag>
                                <input
                                    type="text"
                                    class="form-control bg-white rounded-3 {{ $errors->has('custom_denumire') ? 'is-invalid' : '' }}"
                                    name="custom_denumire"
                                    value="{{ $currentCustomDenumire }}"
                                    placeholder="Ex: Agenda A5 folio 32mm"
                                    autocomplete="off"
                                    data-custom-product-query
                                >
                                <div class="list-group position-absolute w-100 shadow-sm mt-1 d-none" style="z-index: 1050; max-height: 240px; overflow: auto;" data-custom-product-results></div>
                                @if ($errors->has('custom_denumire'))
                                    <div class="invalid-feedback d-block">
                                        {{ $errors->first('custom_denumire') }}
                                    </div>
                                @endif
                            </div>
                            <div class="form-text">Sugestii doar din nomenclatorul de produse custom.</div>
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
                'canWriteMockupuri' => $canWriteMockupuri,
                'canBypassDailyEditLock' => $canBypassDailyEditLock,
                'mockupTypes' => $mockupTypes,
                'clientEmail' => $clientEmail,
            ])
        </div>
        <div class="small mt-2 d-none" data-ajax-message="fisiere"></div>

        @if ($canViewFacturi)
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

                            <form method="POST" action="{{ route('comenzi.facturi.trimite-email', $comanda) }}" data-sync-submit>
                                @csrf
                                <fieldset {{ $canSendFacturaEmail ? '' : 'disabled' }}>
                                <div class="mb-2">
                                    <label class="form-label mb-1">Template</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <select class="form-select" name="template_id" data-email-template-select>
                                            <option value="">Fara template</option>
                                            @foreach ($emailTemplates as $template)
                                                <option value="{{ $template->id }}" style="color: {{ $template->color ?? '#111827' }};">{{ $template->name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="rounded-circle d-inline-block" data-email-color style="width:16px; height:16px; background-color:#6c757d;"></span>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label mb-1">Subiect</label>
                                    <input type="text" name="subject" data-email-subject class="form-control" value="{{ $defaultFacturaSubject }}" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label mb-1">Mesaj</label>
                                    <textarea name="body" data-email-body class="form-control" rows="5" required>{{ $defaultFacturaBody }}</textarea>
                                    <div class="small text-muted mt-1">Mesajul poate fi modificat inainte de trimitere.</div>
                                </div>
                                @include('comenzi.partials.email-mockup-attachments', [
                                    'mockupTypes' => $mockupTypes,
                                    'latestMockupsByType' => $latestMockupsByType,
                                    'selectedMockupLinkTypes' => $selectedMockupLinkTypes,
                                    'inputIdPrefix' => 'factura-email-mockup',
                                ])
                                <div class="mb-3">
                                    <div class="small text-muted">Documente disponibile:</div>
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
                                @if ($canSendFacturaEmail)
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary text-white" {{ $canSendFacturaEmailEnabled ? '' : 'disabled' }}>
                                            <i class="fa-solid fa-paper-plane me-1"></i> Trimite
                                        </button>
                                    </div>
                                @endif
                                </fieldset>
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
                                    $infoLinksLabels = collect(data_get($email->meta, 'info_links', []))
                                        ->map(fn ($item) => trim((($item['type_label'] ?? 'Info') . ': ' . ($item['original_name'] ?? '-'))))
                                        ->implode(', ');
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
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($email->body), 160) }}</div>
                                    <div class="small text-muted">Facturi: {{ $facturiLabels ?: '-' }}</div>
                                    @if ($infoLinksLabels)
                                        <div class="small text-muted">Linkuri info: {{ $infoLinksLabels }}</div>
                                    @endif
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
        @if ($isCerereOferta)
            <div class="d-flex justify-content-end mb-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="plati-toggle" data-plati-toggle>
                    <label class="form-check-label" for="plati-toggle">Activeaza plati</label>
                </div>
            </div>
        @endif
        <div class="row mb-4 {{ $isCerereOferta ? 'plati-disabled' : '' }}" data-plati-section data-plati-disabled-default="{{ $isCerereOferta ? '1' : '0' }}">
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
                              @include('comenzi.partials.plati-table-body', ['comanda' => $comanda, 'metodePlata' => $metodePlata, 'canWritePlati' => $canWritePlati])
                          </tbody>
                      </table>
                  </div>
                  <div data-plati-summary>
                      @include('comenzi.partials.plati-summary', ['comanda' => $comanda, 'statusPlataOptions' => $statusPlataOptions])
                  </div>
                  <form method="POST" action="{{ route('comenzi.plati.store', $comanda) }}" data-ajax-form data-ajax-scope="plati">
                    @csrf
                    <fieldset {{ $canWritePlati ? '' : 'disabled' }}>
                      <div class="row align-items-end">
                        <div class="col-lg-2 mb-2">
                            <label class="mb-0 ps-3">Suma</label>
                            <input type="number" step="0.01" min="0.01" class="form-control bg-white rounded-3" name="suma" required {{ $isCerereOferta ? 'disabled' : '' }}>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="mb-0 ps-3">Metoda</label>
                            <select class="form-select bg-white rounded-3" name="metoda" required {{ $isCerereOferta ? 'disabled' : '' }}>
                                @foreach ($metodePlata as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="mb-0 ps-3">Factura</label>
                            <input type="text" class="form-control bg-white rounded-3" name="numar_factura" {{ $isCerereOferta ? 'disabled' : '' }}>
                        </div>
                        <div class="col-lg-3 mb-2">
                            <label class="mb-0 ps-3">Platit la</label>
                            <input type="datetime-local" class="form-control bg-white rounded-3" name="platit_la" value="{{ now()->format('Y-m-d\\TH:i') }}" required {{ $isCerereOferta ? 'disabled' : '' }}>
                        </div>
                        <div class="col-lg-3 mb-2">
                            <label class="mb-0 ps-3">Note</label>
                            <input type="text" class="form-control bg-white rounded-3" name="note" {{ $isCerereOferta ? 'disabled' : '' }}>
                        </div>
                    </div>
                      <div class="row">
                          <div class="col-lg-12 text-end">
                              @if ($canWritePlati)
                                  <button type="submit" class="btn btn-sm btn-outline-primary" {{ $isCerereOferta ? 'disabled' : '' }}>
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
                                                                            @php
                                                                                $assignmentStatus = $assignmentStatusesByEtapaUser[$etapa->id][(string) $user->id] ?? null;
                                                                            @endphp
                                                                            <label class="form-check-label" for="etapa-{{ $etapa->id }}-user-{{ $user->id }}">
                                                                                {{ $user->name }}
                                                                                @if ($assignmentStatus)
                                                                                    <span class="badge ms-1 {{ $assignmentStatus === 'pending' ? 'bg-warning text-dark' : 'bg-success' }}">
                                                                                        {{ $assignmentStatus === 'pending' ? 'in asteptare' : 'aprobat' }}
                                                                                    </span>
                                                                                @endif
                                                                            </label>
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
                                        @if ($canEditAssignments)
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
            .comanda-shell .product-selector-inline .list-group {
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

                let prepareCustomProductNomenclatorIntent = () => {};

                if (necesarForm) {
                    const customSelectorRoot = necesarForm.querySelector('[data-custom-product-selector]');
                    if (customSelectorRoot) {
                        const searchUrl = customSelectorRoot.dataset.searchUrl;
                        const queryInput = customSelectorRoot.querySelector('[data-custom-product-query]');
                        const selectedIdInput = customSelectorRoot.querySelector('[data-custom-product-id]');
                        const addFlagInput = customSelectorRoot.querySelector('[data-custom-product-add-flag]');
                        const resultsList = customSelectorRoot.querySelector('[data-custom-product-results]');

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
                                resultsList.appendChild(option);
                            });
                            resultsList.classList.remove('d-none');
                        };

                        const setCustomSelection = (item) => {
                            if (!queryInput || !selectedIdInput) return;
                            queryInput.value = item.label || '';
                            selectedIdInput.value = item.id ? String(item.id) : '';
                            closeCustomResults();
                        };

                        const syncSelectionFromExactMatch = () => {
                            if (!queryInput || !selectedIdInput) return;
                            const exact = customOptions.find((item) => normalizeText(item.label) === normalizeText(queryInput.value));
                            if (exact) {
                                selectedIdInput.value = String(exact.id);
                            } else {
                                selectedIdInput.value = '';
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
                                    }
                                }).catch(() => {
                                    selectedIdInput.value = '';
                                });
                            }
                        }

                        prepareCustomProductNomenclatorIntent = () => {
                            if (!addFlagInput) {
                                return;
                            }

                            addFlagInput.value = '0';
                            const selectedMode = necesarForm.querySelector('input[name="produs_tip"]:checked');
                            if (!selectedMode || selectedMode.value !== 'custom') {
                                return;
                            }

                            const typedName = (queryInput?.value || '').trim();
                            if (typedName === '') {
                                return;
                            }

                            if (selectedIdInput && selectedIdInput.value) {
                                return;
                            }

                            const confirmed = window.confirm('Doresti sa adaugi produsul nou in nomenclator?');
                            addFlagInput.value = confirmed ? '1' : '0';
                        };
                    }
                }

                const platiToggle = document.querySelector('[data-plati-toggle]');
                const platiSection = document.querySelector('[data-plati-section]');
                if (platiToggle && platiSection) {
                    const platiControls = () => platiSection.querySelectorAll('input, select, textarea, button');
                    const setPlatiEnabled = (enabled) => {
                        platiControls().forEach((control) => {
                            if (enabled) {
                                control.removeAttribute('disabled');
                            } else {
                                control.setAttribute('disabled', 'disabled');
                            }
                        });
                        platiSection.classList.toggle('plati-disabled', !enabled);
                    };

                    const disabledByDefault = platiSection.dataset.platiDisabledDefault === '1';
                    if (disabledByDefault) {
                        platiToggle.checked = false;
                        setPlatiEnabled(false);
                    }

                    platiToggle.addEventListener('change', () => {
                        setPlatiEnabled(platiToggle.checked);
                    });
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
                    if (payload.gdpr) {
                        const warning = document.querySelector('[data-gdpr-email-warning]');
                        if (warning) {
                            warning.classList.toggle('d-none', !!payload.gdpr.has_consent);
                        }
                        const submitButton = document.querySelector('[data-gdpr-email-submit]');
                        if (submitButton) {
                            submitButton.toggleAttribute('disabled', !payload.gdpr.can_send_email);
                        }
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
                        if (data?.errors) {
                            const firstError = Object.values(data.errors).flat()[0];
                            if (firstError) {
                                message = firstError;
                            }
                        }
                        showAjaxMessage(scope, message, messageType);
                    } finally {
                        restoreFormLoadingState(loadingState);
                    }
                };

                document.addEventListener('submit', (event) => {
                    const form = event.target;
                    if (!form || !form.matches('[data-ajax-form]')) {
                        return;
                    }
                    const confirmMessage = form.dataset.confirm;
                    if (confirmMessage && !window.confirm(confirmMessage)) {
                        event.preventDefault();
                        return;
                    }
                    if (form.matches('[data-necesar-form]')) {
                        prepareCustomProductNomenclatorIntent();
                    }
                    event.preventDefault();
                    submitAjaxForm(form, event.submitter || null);
                });

                document.addEventListener('submit', (event) => {
                    const form = event.target;
                    if (!form || !form.matches('form[data-sync-submit]')) {
                        return;
                    }

                    setFormLoadingState(form, event.submitter || null, { disableControls: false });
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

<div class="modal fade" id="oferta-email-modal" tabindex="-1" aria-labelledby="oferta-email-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="oferta-email-label">Trimite oferta pe e-mail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Inchide"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="small text-muted">Trimite către:</div>
                    <div class="fw-semibold">{{ $clientEmail ?: 'Email lipsă' }}</div>
                </div>

                <form method="POST" action="{{ route('comenzi.pdf.oferta.trimite-email', $comanda) }}" data-sync-submit>
                    @csrf
                    <fieldset {{ $canSendOfertaEmail ? '' : 'disabled' }}>
                                <div class="mb-2">
                                    <label class="form-label mb-1">Template</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <select class="form-select" name="template_id" data-email-template-select>
                                            <option value="">Fara template</option>
                                            @foreach ($emailTemplates as $template)
                                                <option value="{{ $template->id }}" style="color: {{ $template->color ?? '#111827' }};">{{ $template->name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="rounded-circle d-inline-block" data-email-color style="width:16px; height:16px; background-color:#6c757d;"></span>
                                    </div>
                                </div>
                                <div class="mb-2">
                        <label class="form-label mb-1">Subiect</label>
                        <input type="text" name="subject" data-email-subject class="form-control" value="{{ $defaultOfertaSubject }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Mesaj</label>
                        <textarea name="body" data-email-body class="form-control" rows="5" required>{{ $defaultOfertaBody }}</textarea>
                                    <div class="small text-muted mt-1">Mesajul poate fi modificat inainte de trimitere.</div>
                    </div>
                    @include('comenzi.partials.email-mockup-attachments', [
                        'mockupTypes' => $mockupTypes,
                        'latestMockupsByType' => $latestMockupsByType,
                        'selectedMockupLinkTypes' => $selectedMockupLinkTypes,
                        'inputIdPrefix' => 'oferta-email-mockup',
                    ])
                    @if ($canSendOfertaEmail)
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary text-white" {{ $canSendOfertaEmailEnabled ? '' : 'disabled' }}>
                                <i class="fa-solid fa-paper-plane me-1"></i> Trimite
                            </button>
                        </div>
                    @endif
                    </fieldset>
                </form>
                <hr class="my-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-semibold">E-mailuri trimise</div>
                    <span class="badge bg-secondary">{{ $ofertaEmailsCount }}</span>
                </div>
                @forelse ($comanda->ofertaEmails as $email)
                    @php
                        $infoLinksLabels = collect(data_get($email->meta, 'info_links', []))
                            ->map(fn ($item) => trim((($item['type_label'] ?? 'Info') . ': ' . ($item['original_name'] ?? '-'))))
                            ->implode(', ');
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
                        <div class="small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($email->body), 160) }}</div>
                        @if ($email->pdf_name)
                            <div class="small text-muted">Fișier: {{ $email->pdf_name }}</div>
                        @endif
                        @if ($infoLinksLabels)
                            <div class="small text-muted">Linkuri info: {{ $infoLinksLabels }}</div>
                        @endif
                    </div>
                @empty
                    <div class="text-muted small">Nu s-au trimis e-mailuri.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="gdpr-modal" tabindex="-1" aria-labelledby="gdpr-modal-label" aria-hidden="true" data-gdpr-signature-data="{{ $gdprSignatureData ?? '' }}">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content gdpr-modal-content">
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title" id="gdpr-modal-label">Semneaza acordul GDPR</h5>
                    <div class="small text-muted">Clientul semneaza direct in chenarul de mai jos.</div>
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
                        <input type="hidden" name="method" value="signature">
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
                    </form>
                    <div class="small mt-2 d-none" data-ajax-message="gdpr"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="gdpr-email-modal" tabindex="-1" aria-labelledby="gdpr-email-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gdpr-email-label">Trimite acordul GDPR pe e-mail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Inchide"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="small text-muted">Trimite catre:</div>
                    <div class="fw-semibold">{{ $clientEmail ?: 'Email lipsa' }}</div>
                </div>
                <div class="alert alert-warning {{ $gdprHasConsent ? 'd-none' : '' }}" data-gdpr-email-warning>
                    Nu exista un acord GDPR inregistrat.
                </div>
                <form method="POST" action="{{ route('comenzi.pdf.gdpr.trimite-email', $comanda) }}" data-sync-submit>
                    @csrf
                    <fieldset {{ $canSendOfertaEmail ? '' : 'disabled' }}>
                                <div class="mb-2">
                                    <label class="form-label mb-1">Template</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <select class="form-select" name="template_id" data-email-template-select>
                                            <option value="">Fara template</option>
                                            @foreach ($emailTemplates as $template)
                                                <option value="{{ $template->id }}" style="color: {{ $template->color ?? '#111827' }};">{{ $template->name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="rounded-circle d-inline-block" data-email-color style="width:16px; height:16px; background-color:#6c757d;"></span>
                                    </div>
                                </div>
                                <div class="mb-2">
                        <label class="form-label mb-1">Subiect</label>
                        <input type="text" name="subject" data-email-subject class="form-control" value="{{ $defaultGdprSubject }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Mesaj</label>
                        <textarea name="body" data-email-body class="form-control" rows="5" required>{{ $defaultGdprBody }}</textarea>
                                    <div class="small text-muted mt-1">Mesajul poate fi modificat inainte de trimitere.</div>
                    </div>
                    @include('comenzi.partials.email-mockup-attachments', [
                        'mockupTypes' => $mockupTypes,
                        'latestMockupsByType' => $latestMockupsByType,
                        'selectedMockupLinkTypes' => $selectedMockupLinkTypes,
                        'inputIdPrefix' => 'gdpr-email-mockup',
                    ])
                    @if ($canSendOfertaEmail)
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary text-white" data-gdpr-email-submit {{ $canSendGdprEmailEnabled ? '' : 'disabled' }}>
                                <i class="fa-solid fa-paper-plane me-1"></i> Trimite
                            </button>
                        </div>
                    @endif
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    const emailTemplates = @json($emailTemplatePayload);
    const emailPlaceholders = @json($emailPlaceholders);

    const applyEmailPlaceholders = (text) => {
        let output = text || '';
        Object.entries(emailPlaceholders || {}).forEach(([key, value]) => {
            output = output.split(key).join(value ?? '');
        });
        return output;
    };

    const updateEmailTemplate = (select) => {
        const template = emailTemplates[select.value] || null;
        const form = select.closest('form');
        if (!form) return;

        const subjectField = form.querySelector('[data-email-subject]');
        const bodyField = form.querySelector('[data-email-body]');
        const colorPreview = form.querySelector('[data-email-color]');

        if (template) {
            if (subjectField) subjectField.value = applyEmailPlaceholders(template.subject || '');
            if (bodyField) bodyField.value = applyEmailPlaceholders(template.body || '');
            if (colorPreview) colorPreview.style.backgroundColor = template.color || '#6c757d';
        } else if (colorPreview) {
            colorPreview.style.backgroundColor = '#6c757d';
        }
    };

    document.querySelectorAll('[data-email-template-select]').forEach((select) => {
        select.addEventListener('change', () => updateEmailTemplate(select));
        if (select.value) {
            updateEmailTemplate(select);
        }
    });
</script>
@endsection
