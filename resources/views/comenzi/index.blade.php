@extends ('layouts.app')

@section('content')
@php
    $title = $pageTitle ?? 'Comenzi';
    $statusPlataOptions = \App\Enums\StatusPlata::options();
    $currentUser = auth()->user();
    $canWriteComenzi = $currentUser?->hasPermission('comenzi.write') ?? false;
    $canApproveAssignments = $currentUser?->hasPermission('comenzi.etape.write') ?? false;
    $canViewFacturi = $currentUser?->hasAnyPermission(['facturi.view', 'facturi.write']) ?? false;
    $canManageFacturi = $currentUser?->hasPermission('facturi.write') ?? false;
    $canSendFacturaEmail = $currentUser?->hasPermission('facturi.email.send') ?? false;
    $currentSort = $sort ?? null;
    $currentDir = $dir ?? 'asc';
    $sortIcon = function (string $column) use ($currentSort, $currentDir) {
        if ($currentSort === $column) {
            return $currentDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        }

        return 'fa-sort';
    };
    $sortDirFor = function (string $column) use ($currentSort, $currentDir) {
        if ($currentSort !== $column) {
            return 'asc';
        }

        return $currentDir === 'asc' ? 'desc' : 'asc';
    };
    $emailTemplatePayload = $emailTemplates->mapWithKeys(fn ($template) => [
        $template->id => [
            'subject' => $template->subject,
            'body' => $template->body_html,
            'color' => $template->color,
        ],
    ])->all();
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-2">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-clipboard-list"></i> {{ $title }}
            </span>
        </div>

        <div class="col-lg-8">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                @if ($inAsteptareAll)
                    <input type="hidden" name="in_asteptare_all" value="1">
                @endif
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-3 mb-1">
                        <input type="text" class="form-control rounded-3" id="client" name="client" placeholder="Client: nume/telefon/email" value="{{ $client }}">
                    </div>
                    <div class="col-lg-3 mb-1">
                        @if ($fixedTip)
                            <input type="hidden" name="tip" value="{{ $fixedTip }}">
                            <span class="badge bg-secondary w-100 text-start">Tip: {{ $tipuri[$fixedTip] ?? $fixedTip }}</span>
                        @else
                            <select class="form-select rounded-3" name="tip">
                                <option value="">Tip</option>
                                @foreach ($tipuri as $key => $label)
                                    <option value="{{ $key }}" {{ $tip === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="col-lg-3 mb-1">
                        <select class="form-select rounded-3" name="status">
                            <option value="">Status</option>
                            @foreach ($statusuri as $key => $label)
                                <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 mb-1">
                        <select class="form-select rounded-3" name="sursa">
                            <option value="">Sursa</option>
                            @foreach ($surse as $key => $label)
                                <option value="{{ $key }}" {{ $sursa === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 mb-1">
                        <input type="date" class="form-control rounded-3" id="timp_de" name="timp_de" value="{{ $dataDe }}">
                    </div>
                    <div class="col-lg-3 mb-1">
                        <input type="date" class="form-control rounded-3" id="timp_pana" name="timp_pana" value="{{ $dataPana }}">
                    </div>
                    <div class="col-lg-3 mb-1 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="overdue" id="overdue" value="1" {{ $overdue ? 'checked' : '' }}>
                            <label class="form-check-label" for="overdue">Intarziate</label>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-1 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="asignate_mie" id="asignate_mie" value="1" {{ $asignateMie ? 'checked' : '' }}>
                            <label class="form-check-label" for="asignate_mie">Asignate mie</label>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-1 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="in_asteptare" id="in_asteptare" value="1" {{ $inAsteptare ? 'checked' : '' }}>
                            <label class="form-check-label" for="in_asteptare">In asteptare</label>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <button class="btn btn-sm w-100 btn-primary text-white border border-dark rounded-3" type="submit">
                            <i class="fas fa-search text-white me-1"></i>Cauta
                        </button>
                    </div>
                    <div class="col-lg-4">
                        <a class="btn btn-sm w-100 btn-secondary text-white border border-dark rounded-3" href="{{ url()->current() }}" role="button">
                            <i class="far fa-trash-alt text-white me-1"></i>Reseteaza cautarea
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-2 text-end">
            @if ($canWriteComenzi)
                <a class="btn btn-sm btn-success text-white border border-dark rounded-3 col-md-8" href="{{ route('comenzi.create') }}" role="button">
                    <i class="fas fa-plus text-white me-1"></i> Adauga comanda
                </a>
            @endif
        </div>
    </div>

    <div class="card-body px-0 py-3">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Comenzi table">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2 text-nowrap" width="5%"><i class="fa-solid fa-hashtag"></i></th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="25%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'client', 'dir' => $sortDirFor('client')]) }}">
                                <i class="fa-solid fa-user me-1"></i> Client
                                <i class="fa-solid {{ $sortIcon('client') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'solicitare', 'dir' => $sortDirFor('solicitare')]) }}">
                                <i class="fa-solid fa-calendar-day me-1"></i> Data solicitarii
                                <i class="fa-solid {{ $sortIcon('solicitare') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'tip', 'dir' => $sortDirFor('tip')]) }}">
                                <i class="fa-solid fa-tag me-1"></i> Tip
                                <i class="fa-solid {{ $sortIcon('tip') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="15%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'status', 'dir' => $sortDirFor('status')]) }}">
                                <i class="fa-solid fa-list-check me-1"></i> Status
                                <i class="fa-solid {{ $sortIcon('status') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'sursa', 'dir' => $sortDirFor('sursa')]) }}">
                                <i class="fa-solid fa-circle-up me-1"></i> Sursa
                                <i class="fa-solid {{ $sortIcon('sursa') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="15%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'livrare', 'dir' => $sortDirFor('livrare')]) }}">
                                <i class="fa-solid fa-clock me-1"></i> Livrare
                                <i class="fa-solid {{ $sortIcon('livrare') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'total', 'dir' => $sortDirFor('total')]) }}">
                                <i class="fa-solid fa-money-bill me-1"></i> Total
                                <i class="fa-solid {{ $sortIcon('total') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap" width="10%">
                            <a class="text-white text-decoration-none" href="{{ request()->fullUrlWithQuery(['sort' => 'plata', 'dir' => $sortDirFor('plata')]) }}">
                                <i class="fa-solid fa-credit-card me-1"></i> Plata
                                <i class="fa-solid {{ $sortIcon('plata') }} ms-1"></i>
                            </a>
                        </th>
                        <th scope="col" class="text-white culoare2 text-nowrap text-center" width="10%"><i class="fa-solid fa-comment-sms me-1"></i> SMS</th>
                        <th scope="col" class="text-white culoare2 text-nowrap text-center" width="10%"><i class="fa-solid fa-envelope me-1"></i> Email</th>
                        <th scope="col" class="text-white culoare2 text-nowrap text-center" width="12%"><i class="fa-solid fa-file-invoice me-1"></i> Facturi</th>
                        <th scope="col" class="text-white culoare2 text-nowrap text-end" width="10%"><i class="fa-solid fa-cogs me-1"></i> Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($comenzi as $comanda)
                        @php
                            $rowClass = '';
                            if ($comanda->is_overdue) {
                                $rowClass = 'bg-danger';
                            } elseif ($comanda->is_due_soon) {
                                $rowClass = 'bg-warning';
                            }
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td>
                                {{ ($comenzi->currentpage()-1) * $comenzi->perpage() + $loop->index + 1 }}
                            </td>
                            <td>
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div>{{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                                    <div class="small text-muted">{{ optional($comanda->client)->telefon }}</div>
                                    @if (optional($comanda->client)->telefon_secundar)
                                        <div class="small text-muted">{{ optional($comanda->client)->telefon_secundar }}</div>
                                    @endif
                                    </div>
                            @if (($comanda->pending_etapa_assignments_count ?? 0) > 0)
                                @if ($canApproveAssignments)
                                    <form method="POST" action="{{ route('comenzi.aproba-cerere', $comanda) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-warning text-dark text-nowrap">
                                            APROBA CEREREA
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </td>
                    <td>
                        {{ optional($comanda->data_solicitarii)->format('d.m.Y') }}
                    </td>
                    <td>
                        {{ $tipuri[$comanda->tip] ?? $comanda->tip }}
                    </td>
                            <td>
                                <div>{{ $statusuri[$comanda->status] ?? $comanda->status }}</div>
                                @if ($comanda->is_overdue)
                                    <span class="badge bg-danger">Intarziata</span>
                                @elseif ($comanda->is_due_soon)
                                    <span class="badge bg-warning text-dark">In urmatoarele 24h</span>
                                @endif
                            </td>
                            <td>
                                {{ $surse[$comanda->sursa] ?? $comanda->sursa }}
                            </td>
                            <td>
                                {{ optional($comanda->timp_estimat_livrare)->format('d.m.Y H:i') }}
                            </td>
                            <td>
                                {{ number_format($comanda->total, 2) }}
                            </td>
                            <td>
                                {{ $statusPlataOptions[$comanda->status_plata] ?? $comanda->status_plata }}
                            </td>
                            <td class="text-center">
                                @php
                                    $smsCount = (int) ($comanda->sms_messages_count ?? $comanda->smsMessages->count());
                                @endphp
                                <a
                                    class="btn p-0 border-0 bg-transparent"
                                    href="{{ route('comenzi.sms.show', $comanda) }}"
                                    aria-label="Trimite SMS"
                                    title="Trimite SMS"
                                >
                                    <span class="badge bg-info text-dark">
                                        <i class="fa-solid fa-comment-sms me-1"></i>{{ $smsCount }}
                                    </span>
                                </a>
                            </td>
                            <td class="text-center">
                                @php
                                    $emailCount = (int) ($comanda->oferta_emails_count ?? 0)
                                        + (int) ($comanda->factura_emails_count ?? 0)
                                        + (int) ($comanda->email_logs_count ?? 0);
                                @endphp
                                <a
                                    class="btn p-0 border-0 bg-transparent"
                                    href="{{ route('comenzi.email.show', $comanda) }}"
                                    aria-label="Trimite email"
                                    title="Trimite email"
                                >
                                    <span class="badge bg-secondary">
                                        <i class="fa-solid fa-envelope me-1"></i>{{ $emailCount }}
                                    </span>
                                </a>
                            </td>
                            <td class="text-end">
                                @if ($canViewFacturi)
                                    @php
                                        $clientEmail = optional($comanda->client)->email;
                                        $facturiCount = (int) ($comanda->facturi_count ?? $comanda->facturi->count());
                                        $hasFacturi = $facturiCount > 0;
                                        $hasClientEmail = !empty($clientEmail);
                                        $facturaEmailsCount = (int) ($comanda->factura_emails_count ?? $comanda->facturaEmails->count());
                                    @endphp
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        <button
                                            type="button"
                                            class="btn p-0 border-0 bg-transparent"
                                            data-bs-toggle="modal"
                                            data-bs-target="#factura-upload-{{ $comanda->id }}"
                                            title="Incarca facturi"
                                            aria-label="Incarca facturi"
                                        >
                                            <span class="badge bg-primary">
                                                <i class="fa-solid fa-upload me-1"></i>{{ $facturiCount }}
                                            </span>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn p-0 border-0 bg-transparent"
                                            data-bs-toggle="modal"
                                            data-bs-target="#factura-email-{{ $comanda->id }}"
                                            title="Trimite email factura"
                                            aria-label="Trimite email factura"
                                        >
                                            <span class="badge bg-secondary">
                                                <i class="fa-solid fa-paper-plane me-1"></i>{{ $facturaEmailsCount }}
                                            </span>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-muted small">Doar supervizorii</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex justify-content-end py-0">
                                    <a href="{{ route('comenzi.show', $comanda) }}" class="flex me-1" aria-label="Vezi comanda {{ $comanda->id }}">
                                        <span class="badge bg-success"><i class="fa-solid fa-eye"></i></span>
                                    </a>
                                    @if ($canWriteComenzi)
                                        <form method="POST" action="{{ route('comenzi.destroy', $comanda) }}" onsubmit="return confirm('Sigur vrei sa stergi aceasta comanda?')">
                                            @method('DELETE')
                                            @csrf
                                            <button type="submit" class="badge bg-danger border-0" aria-label="Sterge comanda {{ $comanda->id }}">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted py-5">
                                <i class="fa-solid fa-clipboard-list fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu s-au gasit comenzi in baza de date.</p>
                                @if($client || $status || $sursa || $tip || $dataDe || $dataPana || $overdue || $asignateMie || $inAsteptare || $inAsteptareAll)
                                    <p class="small mb-0 mt-2">Incearca sa modifici criteriile de cautare.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($canViewFacturi)
            @foreach ($comenzi as $comanda)
                @php
                    $clientEmail = optional($comanda->client)->email;
                    $facturi = $comanda->facturi ?? collect();
                    $facturiCount = (int) ($comanda->facturi_count ?? $facturi->count());
                    $facturaEmails = $comanda->facturaEmails ?? collect();
                    $facturaEmailsCount = (int) ($comanda->factura_emails_count ?? $facturaEmails->count());
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
                    $defaultSubject = "Factura {$appName} - {$subjectClientName} - comanda #{$comanda->id}";
                    $defaultBody = "Buna ziua {$subjectClientName},\n\nPuteti descarca factura {$appName} pentru comanda #{$comanda->id} folosind butonul din email.\n\n{$orderSummary}Va multumim,\n{$appName}";
                    $emailPlaceholders = \App\Support\EmailPlaceholders::forComanda($comanda);
                @endphp

                <div class="modal fade" id="factura-upload-{{ $comanda->id }}" tabindex="-1" aria-labelledby="factura-upload-label-{{ $comanda->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="factura-upload-label-{{ $comanda->id }}">Facturi comanda #{{ $comanda->id }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Inchide"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" action="{{ route('comenzi.facturi.store', $comanda) }}" enctype="multipart/form-data" class="mb-3">
                                    @csrf
                                    <fieldset {{ $canManageFacturi ? '' : 'disabled' }}>
                                        <div class="input-group">
                                            <input type="file" class="form-control" name="factura[]" multiple required>
                                            @if ($canManageFacturi)
                                                <button type="submit" class="btn p-0 border-0 bg-transparent" title="Incarca facturi" aria-label="Incarca facturi">
                                                    <span class="badge bg-primary">
                                                        <i class="fa-solid fa-upload me-1"></i>{{ $facturiCount }}
                                                    </span>
                                                </button>
                                            @endif
                                        </div>
                                        <div class="small text-muted mt-2">Se pot incarca mai multe facturi odata.</div>
                                    </fieldset>
                                </form>

                                <div class="fw-semibold mb-2">Facturi existente</div>
                                <ul class="list-group">
                                    @forelse ($facturi as $factura)
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
                                                @if ($canManageFacturi)
                                                    <form method="POST" action="{{ $factura->destroyUrl() }}" onsubmit="return confirm('Stergi factura?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Sterge" aria-label="Sterge">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </li>
                                    @empty
                                        <li class="list-group-item text-muted">Nu exista facturi.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="factura-email-{{ $comanda->id }}" tabindex="-1" aria-labelledby="factura-email-label-{{ $comanda->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="factura-email-label-{{ $comanda->id }}">Trimite factura pe e-mail</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Inchide"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <div class="small text-muted">Trimite către:</div>
                                    <div class="fw-semibold">{{ $clientEmail ?: 'Email lipsă' }}</div>
                                </div>

                                <form method="POST" action="{{ route('comenzi.facturi.trimite-email', $comanda) }}" data-email-placeholders='@json($emailPlaceholders)'>
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
                                        <input type="text" name="subject" data-email-subject class="form-control" value="{{ $defaultSubject }}" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label mb-1">Mesaj</label>
                                        <textarea name="body" data-email-body class="form-control" rows="5" required>{{ $defaultBody }}</textarea>
                                        <div class="small text-muted mt-1">Mesajul poate fi modificat inainte de trimitere.</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="small text-muted">Documente disponibile:</div>
                                        @if ($facturiCount)
                                            <ul class="small mb-0">
                                                @foreach ($facturi as $factura)
                                                    <li>{{ $factura->original_name }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <div class="text-muted small">Nu există facturi încărcate.</div>
                                        @endif
                                    </div>
                                    @if ($canSendFacturaEmail)
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary text-white" {{ $canSendFacturaEmail && $facturiCount && $clientEmail ? '' : 'disabled' }}>
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
                                @forelse ($facturaEmails as $email)
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
                                        <div class="small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($email->body), 160) }}</div>
                                        <div class="small text-muted">Facturi: {{ $facturiLabels ?: '-' }}</div>
                                    </div>
                                @empty
                                    <div class="text-muted small">Nu s-au trimis e-mailuri.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif

        <nav>
            <ul class="pagination justify-content-center">
                {{ $comenzi->appends(Request::except('page'))->links() }}
            </ul>
        </nav>
    </div>
</div>
<script>
    const emailTemplates = @json($emailTemplatePayload);

    const applyEmailPlaceholders = (text, placeholders) => {
        let output = text || '';
        Object.entries(placeholders || {}).forEach(([key, value]) => {
            output = output.split(key).join(value ?? '');
        });
        return output;
    };

    const parsePlaceholders = (form) => {
        if (!form) return {};
        const raw = form.dataset.emailPlaceholders || '{}';
        try {
            return JSON.parse(raw);
        } catch (error) {
            return {};
        }
    };

    const updateEmailTemplate = (select) => {
        const template = emailTemplates[select.value] || null;
        const form = select.closest('form');
        const placeholders = parsePlaceholders(form);
        const subjectField = form?.querySelector('[data-email-subject]');
        const bodyField = form?.querySelector('[data-email-body]');
        const colorPreview = form?.querySelector('[data-email-color]');

        if (template) {
            if (subjectField) subjectField.value = applyEmailPlaceholders(template.subject || '', placeholders);
            if (bodyField) bodyField.value = applyEmailPlaceholders(template.body || '', placeholders);
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
