@extends ('layouts.app')

@section('content')
@php
    $title = $pageTitle ?? 'Comenzi';
    $statusPlataOptions = \App\Enums\StatusPlata::options();
    $currentUser = auth()->user();
    $canWriteComenzi = $currentUser?->hasPermission('comenzi.write') ?? false;
    $canBulkActionsComenzi = $canWriteComenzi;
    $canApproveAssignments = $currentUser?->hasPermission('comenzi.etape.write') ?? false;
    $canViewFacturi = $currentUser?->hasAnyPermission(['facturi.view', 'facturi.write']) ?? false;
    $canManageFacturi = $currentUser?->hasPermission('facturi.write') ?? false;
    $canSendFacturaEmail = $currentUser?->hasPermission('facturi.email.send') ?? false;
    $showInvoiceEmailPopup = (bool) config('features.order_invoice_email_popup_enabled', false);
    $showFacturiColumn = $showInvoiceEmailPopup;
    $emptyColspan = $showFacturiColumn ? 13 : 12;
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
    $facturaModalPayload = [];
    if ($canViewFacturi && $showFacturiColumn) {
        foreach ($comenzi as $comandaForPayload) {
            $facturi = $comandaForPayload->facturi ?? collect();
            $facturaEmails = $comandaForPayload->facturaEmails ?? collect();
            $clientEmail = (string) (optional($comandaForPayload->client)->email ?? '');
            $clientName = trim((string) (optional($comandaForPayload->client)->nume_complet ?? ''));
            $appName = (string) config('app.name');
            $subjectClientName = $clientName !== '' ? $clientName : 'Client';
            $orderLines = $comandaForPayload->produse
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

            $facturaModalPayload[$comandaForPayload->id] = [
                'id' => $comandaForPayload->id,
                'client_email' => $clientEmail,
                'default_subject' => "Factura {$appName} - {$subjectClientName} - comanda #{$comandaForPayload->id}",
                'default_body' => "Buna ziua {$subjectClientName},\n\nPuteti descarca factura {$appName} pentru comanda #{$comandaForPayload->id} folosind butonul din email.\n\n{$orderSummary}Va multumim,\n{$appName}",
                'email_placeholders' => \App\Support\EmailPlaceholders::forComanda($comandaForPayload),
                'facturi' => $facturi->map(function ($factura) {
                    return [
                        'id' => $factura->id,
                        'original_name' => $factura->original_name,
                        'size_kb' => round(((float) $factura->size) / 1024, 1),
                        'uploaded_by' => $factura->uploadedBy?->name,
                        'created_at' => optional($factura->created_at)->format('d.m.Y H:i'),
                        'view_url' => $factura->fileUrl(),
                        'download_url' => $factura->downloadUrl(),
                        'destroy_url' => $factura->destroyUrl(),
                    ];
                })->values()->all(),
                'factura_emails' => $facturaEmails->map(function ($email) {
                    $facturiSnapshot = collect($email->facturi ?? []);

                    return [
                        'created_at' => optional($email->created_at)->format('d.m.Y H:i'),
                        'recipient' => $email->recipient,
                        'sent_by' => $email->sentBy?->name,
                        'subject' => $email->subject,
                        'body_preview' => \Illuminate\Support\Str::limit(strip_tags((string) $email->body), 160),
                        'facturi_labels' => $facturiSnapshot->pluck('original_name')->filter()->implode(', '),
                    ];
                })->values()->all(),
            ];
        }
    }
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-2">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-clipboard-list"></i> {{ $title }}
            </span>
        </div>

        <div class="col-lg-7">
            <form class="needs-validation" novalidate method="GET" action="{{ url()->current() }}">
                @if ($inAsteptareAll)
                    <input type="hidden" name="in_asteptare_all" value="1">
                @endif
                @if ($fixedTip)
                    <input type="hidden" name="tip" value="{{ $fixedTip }}">
                @endif
                <div class="row mb-1 custom-search-form justify-content-center">
                    <div class="col-lg-3 mb-1">
                        <input type="text" class="form-control rounded-3" id="nr_comanda" name="nr_comanda" placeholder="Nr comanda (ID)" value="{{ $nrComanda ?? '' }}" inputmode="numeric">
                    </div>
                    <div class="col-lg-3 mb-1">
                        <input type="text" class="form-control rounded-3" id="client" name="client" placeholder="Client: nume/telefon/email" value="{{ $client }}">
                    </div>
                    @if (!$fixedTip)
                        <div class="col-lg-3 mb-1">
                            <select class="form-select rounded-3" name="tip">
                                <option value="">Tip</option>
                                @foreach ($tipuri as $key => $label)
                                    <option value="{{ $key }}" {{ $tip === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
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

        <div class="col-lg-3 d-flex flex-column justify-content-between" style="min-height: 110px;">
            <div class="text-end">
                @if ($canWriteComenzi)
                    <a class="btn btn-sm btn-success text-white border border-dark rounded-3" href="{{ route('comenzi.create') }}" role="button">
                        <i class="fas fa-plus text-white me-1"></i> Adauga comanda
                    </a>
                @endif
            </div>
            @if ($canBulkActionsComenzi)
                <div class="d-flex flex-column align-items-end gap-2 mt-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger border border-dark rounded-3"
                        data-comanda-bulk-delete
                    >
                        <i class="fa-solid fa-trash me-1"></i> Sterge selectate
                    </button>
                    <a
                        class="btn btn-sm btn-outline-secondary border border-dark rounded-3"
                        href="{{ $trashRoute }}"
                    >
                        <i class="fa-solid fa-trash-can-arrow-up me-1"></i> Vezi trash
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="card-body px-0 py-3">
        @include ('errors.errors')

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover rounded" aria-label="Comenzi table">
                <thead class="text-white rounded">
                    <tr class="thead-danger" style="padding:2rem">
                        <th scope="col" class="text-white culoare2 text-nowrap" width="6%">
                            <div class="d-flex align-items-center gap-2">
                                @if ($canBulkActionsComenzi)
                                    <input type="checkbox" class="form-check-input" data-comanda-select-all aria-label="Selecteaza toate comenzile">
                                @endif
                                <i class="fa-solid fa-hashtag"></i>
                            </div>
                        </th>
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
                        @if ($showFacturiColumn)
                            {{-- TODO(2026-02-19): Temporary hidden via features.order_invoice_email_popup_enabled.
                                 Remove Facturi column markup entirely after 2026-02-26 if users confirm it is not needed. --}}
                            <th scope="col" class="text-white culoare2 text-nowrap text-center" width="12%"><i class="fa-solid fa-file-invoice me-1"></i> Facturi</th>
                        @endif
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
                                <div class="d-flex align-items-start gap-2">
                                    @if ($canBulkActionsComenzi)
                                        <input type="checkbox" class="form-check-input" value="{{ $comanda->id }}" data-comanda-select aria-label="Selecteaza comanda {{ $comanda->id }}">
                                    @endif
                                    <div class="d-flex flex-column lh-sm">
                                        <span class="fw-semibold">#{{ $comanda->id }}</span>
                                        <span class="small text-muted">{{ ($comenzi->currentpage()-1) * $comenzi->perpage() + $loop->index + 1 }}</span>
                                    </div>
                                </div>
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
                            @if ($showFacturiColumn)
                                <td class="text-end">
                                    @if ($canViewFacturi)
                                        @php
                                            $facturiCount = (int) ($comanda->facturi_count ?? $comanda->facturi->count());
                                            $facturaEmailsCount = (int) ($comanda->factura_emails_count ?? $comanda->facturaEmails->count());
                                        @endphp
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <button
                                                type="button"
                                                class="btn p-0 border-0 bg-transparent"
                                                data-bs-toggle="modal"
                                                data-bs-target="#factura-upload-modal"
                                                data-factura-upload-trigger
                                                data-comanda-id="{{ $comanda->id }}"
                                                title="Incarca facturi"
                                                aria-label="Incarca facturi"
                                            >
                                                <span class="badge bg-primary">
                                                    <i class="fa-solid fa-upload me-1"></i>{{ $facturiCount }}
                                                </span>
                                            </button>
                                            @if ($showInvoiceEmailPopup)
                                                <button
                                                    type="button"
                                                    class="btn p-0 border-0 bg-transparent"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#factura-email-modal"
                                                    data-factura-email-trigger
                                                    data-comanda-id="{{ $comanda->id }}"
                                                    title="Trimite email factura"
                                                    aria-label="Trimite email factura"
                                                >
                                                    <span class="badge bg-secondary">
                                                        <i class="fa-solid fa-paper-plane me-1"></i>{{ $facturaEmailsCount }}
                                                    </span>
                                                </button>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">Doar supervizorii</span>
                                    @endif
                                </td>
                            @endif
                            <td>
                                <div class="d-flex justify-content-end py-0">
                                    <a href="{{ route('comenzi.show', $comanda) }}" class="flex me-1" aria-label="Vezi comanda {{ $comanda->id }}">
                                        <span class="badge bg-success"><i class="fa-solid fa-eye"></i></span>
                                    </a>
                                    @if ($canWriteComenzi)
                                        <form method="POST" action="{{ route('comenzi.duplicate', $comanda) }}" class="me-1" data-confirm="Sigur vrei sa duplici aceasta comanda?">
                                            @csrf
                                            <button type="submit" class="badge bg-warning text-dark border-0" aria-label="Duplica comanda {{ $comanda->id }}">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('comenzi.destroy', $comanda) }}" data-confirm="Sigur vrei sa stergi aceasta comanda? Va fi mutata in trash.">
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
                            <td colspan="{{ $emptyColspan }}" class="text-center text-muted py-5">
                                <i class="fa-solid fa-clipboard-list fa-2x mb-3 d-block"></i>
                                <p class="mb-0">Nu s-au gasit comenzi in baza de date.</p>
                                @if($nrComanda || $client || $status || $sursa || $tip || $dataDe || $dataPana || $overdue || $asignateMie || $inAsteptare || $inAsteptareAll)
                                    <p class="small mb-0 mt-2">Incearca sa modifici criteriile de cautare.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($canViewFacturi && $showFacturiColumn)
            <div class="modal fade" id="factura-upload-modal" tabindex="-1" aria-labelledby="factura-upload-label" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="factura-upload-label" data-factura-upload-title>Facturi comanda</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Inchide"></button>
                        </div>
                        <div class="modal-body">
                            <form
                                method="POST"
                                action=""
                                enctype="multipart/form-data"
                                class="mb-3"
                                data-factura-upload-form
                                data-action-template="{{ route('comenzi.facturi.store', ['comanda' => '__COMANDA__']) }}"
                            >
                                @csrf
                                <fieldset {{ $canManageFacturi ? '' : 'disabled' }}>
                                    <div class="input-group">
                                        <input type="file" class="form-control" name="factura[]" multiple required>
                                        @if ($canManageFacturi)
                                            <button type="submit" class="btn btn-primary text-white" title="Incarca facturi" aria-label="Incarca facturi">
                                                <i class="fa-solid fa-upload me-1"></i>Incarca
                                            </button>
                                        @endif
                                    </div>
                                    <div class="small text-muted mt-2">Se pot incarca mai multe facturi odata. Maxim 10MB per fisier.</div>
                                </fieldset>
                            </form>

                            <div class="fw-semibold mb-2">Facturi existente</div>
                            <ul class="list-group" data-factura-upload-list>
                                <li class="list-group-item text-muted">Selecteaza o comanda.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            @if ($showInvoiceEmailPopup)
                {{-- TODO(2026-02-19): Temporary hidden via features.order_invoice_email_popup_enabled.
                     Remove this popup block entirely after 2026-02-26 if users confirm it is not needed. --}}
                <div class="modal fade" id="factura-email-modal" tabindex="-1" aria-labelledby="factura-email-label" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="factura-email-label" data-factura-email-title>Trimite factura pe e-mail</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Inchide"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <div class="small text-muted">Trimite catre:</div>
                                    <div class="fw-semibold" data-factura-email-recipient>Email lipsa</div>
                                </div>

                                <form
                                    method="POST"
                                    action=""
                                    data-email-placeholders="{}"
                                    data-factura-email-form
                                    data-action-template="{{ route('comenzi.facturi.trimite-email', ['comanda' => '__COMANDA__']) }}"
                                >
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
                                            <input type="text" name="subject" data-email-subject class="form-control" value="" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label mb-1">Mesaj</label>
                                            <textarea name="body" data-email-body class="form-control" rows="5" required></textarea>
                                            <div class="small text-muted mt-1">Mesajul poate fi modificat inainte de trimitere.</div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="small text-muted">Documente disponibile:</div>
                                            <ul class="small mb-0 d-none" data-factura-email-docs></ul>
                                            <div class="text-muted small" data-factura-email-docs-empty>Nu exista facturi incarcate.</div>
                                        </div>
                                        @if ($canSendFacturaEmail)
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary text-white" data-factura-email-submit>
                                                    <i class="fa-solid fa-paper-plane me-1"></i> Trimite
                                                </button>
                                            </div>
                                        @endif
                                    </fieldset>
                                </form>

                                <hr class="my-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-semibold">E-mailuri trimise</div>
                                    <span class="badge bg-secondary" data-factura-email-history-count>0</span>
                                </div>
                                <div data-factura-email-history>
                                    <div class="text-muted small">Selecteaza o comanda.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if ($comenzi->total() > 0)
            <div class="d-flex flex-wrap justify-content-between align-items-center px-3 mt-2 mb-3 small text-muted">
                <span>Afisare {{ $comenzi->firstItem() }}-{{ $comenzi->lastItem() }} din {{ $comenzi->total() }} comenzi</span>
                <span>Pagina {{ $comenzi->currentPage() }} din {{ $comenzi->lastPage() }}</span>
            </div>
        @endif

        <nav>
            <ul class="pagination justify-content-center">
                {{ $comenzi->appends(Request::except('page'))->links() }}
            </ul>
        </nav>

    </div>
</div>
@if ($canBulkActionsComenzi)
    <form id="comenzi-bulk-delete-form" method="POST" action="{{ route('comenzi.bulk-destroy') }}" class="d-none">
        @csrf
        @method('DELETE')
        <div data-comanda-bulk-inputs></div>
    </form>
@endif
<script>
    const emailTemplates = @json($emailTemplatePayload);
    const facturaModalPayload = @json($facturaModalPayload);
    const canManageFacturi = @json($canManageFacturi);
    const canSendFacturaEmail = @json($canSendFacturaEmail);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

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

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');
    const resolveActionFromTemplate = (template, comandaId) => String(template || '').replace('__COMANDA__', String(comandaId || ''));
    const getFacturaPayload = (comandaId) => facturaModalPayload[String(comandaId)] || null;

    const facturaUploadModal = document.getElementById('factura-upload-modal');
    const facturaUploadTitle = facturaUploadModal?.querySelector('[data-factura-upload-title]');
    const facturaUploadForm = facturaUploadModal?.querySelector('[data-factura-upload-form]');
    const facturaUploadList = facturaUploadModal?.querySelector('[data-factura-upload-list]');

    const facturaEmailModal = document.getElementById('factura-email-modal');
    const facturaEmailTitle = facturaEmailModal?.querySelector('[data-factura-email-title]');
    const facturaEmailRecipient = facturaEmailModal?.querySelector('[data-factura-email-recipient]');
    const facturaEmailForm = facturaEmailModal?.querySelector('[data-factura-email-form]');
    const facturaEmailTemplateSelect = facturaEmailModal?.querySelector('[data-email-template-select]');
    const facturaEmailSubject = facturaEmailModal?.querySelector('[data-email-subject]');
    const facturaEmailBody = facturaEmailModal?.querySelector('[data-email-body]');
    const facturaEmailColor = facturaEmailModal?.querySelector('[data-email-color]');
    const facturaEmailDocs = facturaEmailModal?.querySelector('[data-factura-email-docs]');
    const facturaEmailDocsEmpty = facturaEmailModal?.querySelector('[data-factura-email-docs-empty]');
    const facturaEmailSubmit = facturaEmailModal?.querySelector('[data-factura-email-submit]');
    const facturaEmailHistoryWrap = facturaEmailModal?.querySelector('[data-factura-email-history]');
    const facturaEmailHistoryCount = facturaEmailModal?.querySelector('[data-factura-email-history-count]');

    const renderFacturiList = (payload) => {
        const facturi = Array.isArray(payload?.facturi) ? payload.facturi : [];
        if (!facturi.length) {
            return '<li class="list-group-item text-muted">Nu exista facturi.</li>';
        }

        return facturi.map((factura) => {
            const sizeKb = Number(factura?.size_kb || 0);
            const uploadedBy = factura?.uploaded_by ? ` - ${escapeHtml(factura.uploaded_by)}` : '';
            const createdAt = factura?.created_at ? ` - ${escapeHtml(factura.created_at)}` : '';
            const deleteAction = canManageFacturi
                ? `
                    <form method="POST" action="${escapeHtml(factura?.destroy_url || '')}" data-confirm="Stergi factura?" class="d-inline">
                        <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-sm btn-danger" title="Sterge" aria-label="Sterge">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                `
                : '';

            return `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="me-2">
                        <a href="${escapeHtml(factura?.view_url || '#')}" target="_blank" rel="noopener">${escapeHtml(factura?.original_name || '-')}</a>
                        <div class="small text-muted">${sizeKb.toFixed(1)} KB${uploadedBy}${createdAt}</div>
                    </div>
                    <div class="d-flex gap-1">
                        <a class="btn btn-sm btn-primary" href="${escapeHtml(factura?.view_url || '#')}" target="_blank" rel="noopener" title="Vezi" aria-label="Vezi">
                            <i class="fa-regular fa-eye"></i>
                        </a>
                        <a class="btn btn-sm btn-success" href="${escapeHtml(factura?.download_url || '#')}" title="Download" aria-label="Download">
                            <i class="fa-solid fa-download"></i>
                        </a>
                        ${deleteAction}
                    </div>
                </li>
            `;
        }).join('');
    };

    const renderFacturaEmailHistory = (payload) => {
        const history = Array.isArray(payload?.factura_emails) ? payload.factura_emails : [];
        if (!history.length) {
            return '<div class="text-muted small">Nu s-au trimis e-mailuri.</div>';
        }

        return history.map((email) => {
            const sentBy = email?.sent_by ? ` - ${escapeHtml(email.sent_by)}` : '';
            return `
                <div class="border rounded-3 p-2 mb-2">
                    <div class="small text-muted">
                        ${escapeHtml(email?.created_at || '-')} - ${escapeHtml(email?.recipient || '-')}${sentBy}
                    </div>
                    <div class="fw-semibold">${escapeHtml(email?.subject || '-')}</div>
                    <div class="small text-muted">${escapeHtml(email?.body_preview || '')}</div>
                    <div class="small text-muted">Facturi: ${escapeHtml(email?.facturi_labels || '-')}</div>
                </div>
            `;
        }).join('');
    };

    const populateFacturaUploadModal = (comandaId) => {
        const payload = getFacturaPayload(comandaId);
        if (!payload || !facturaUploadModal || !facturaUploadForm) {
            return;
        }

        if (facturaUploadTitle) {
            facturaUploadTitle.textContent = `Facturi comanda #${payload.id}`;
        }
        facturaUploadForm.action = resolveActionFromTemplate(facturaUploadForm.dataset.actionTemplate, payload.id);
        facturaUploadForm.reset();
        if (facturaUploadList) {
            facturaUploadList.innerHTML = renderFacturiList(payload);
        }
    };

    const populateFacturaEmailModal = (comandaId) => {
        const payload = getFacturaPayload(comandaId);
        if (!payload || !facturaEmailModal || !facturaEmailForm) {
            return;
        }

        if (facturaEmailTitle) {
            facturaEmailTitle.textContent = `Trimite factura pe e-mail - comanda #${payload.id}`;
        }
        if (facturaEmailRecipient) {
            facturaEmailRecipient.textContent = payload.client_email || 'Email lipsa';
        }
        facturaEmailForm.action = resolveActionFromTemplate(facturaEmailForm.dataset.actionTemplate, payload.id);
        facturaEmailForm.dataset.emailPlaceholders = JSON.stringify(payload.email_placeholders || {});

        if (facturaEmailTemplateSelect) {
            facturaEmailTemplateSelect.value = '';
        }
        if (facturaEmailSubject) {
            facturaEmailSubject.value = payload.default_subject || '';
        }
        if (facturaEmailBody) {
            facturaEmailBody.value = payload.default_body || '';
        }
        if (facturaEmailColor) {
            facturaEmailColor.style.backgroundColor = '#6c757d';
        }

        const facturi = Array.isArray(payload.facturi) ? payload.facturi : [];
        if (facturaEmailDocs && facturaEmailDocsEmpty) {
            if (facturi.length > 0) {
                facturaEmailDocs.innerHTML = facturi
                    .map((factura) => `<li>${escapeHtml(factura?.original_name || '-')}</li>`)
                    .join('');
                facturaEmailDocs.classList.remove('d-none');
                facturaEmailDocsEmpty.classList.add('d-none');
            } else {
                facturaEmailDocs.innerHTML = '';
                facturaEmailDocs.classList.add('d-none');
                facturaEmailDocsEmpty.classList.remove('d-none');
            }
        }

        if (facturaEmailSubmit) {
            facturaEmailSubmit.disabled = !(canSendFacturaEmail && facturi.length > 0 && payload.client_email);
        }
        if (facturaEmailHistoryCount) {
            facturaEmailHistoryCount.textContent = String((payload.factura_emails || []).length);
        }
        if (facturaEmailHistoryWrap) {
            facturaEmailHistoryWrap.innerHTML = renderFacturaEmailHistory(payload);
        }
    };

    document.addEventListener('click', (event) => {
        const uploadTrigger = event.target.closest('[data-factura-upload-trigger]');
        if (uploadTrigger) {
            populateFacturaUploadModal(uploadTrigger.dataset.comandaId || '');
            return;
        }

        const emailTrigger = event.target.closest('[data-factura-email-trigger]');
        if (emailTrigger) {
            populateFacturaEmailModal(emailTrigger.dataset.comandaId || '');
        }
    });

    document.querySelectorAll('[data-email-template-select]').forEach((select) => {
        select.addEventListener('change', () => updateEmailTemplate(select));
        if (select.value) {
            updateEmailTemplate(select);
        }
    });

    const selectAllComenzi = document.querySelector('[data-comanda-select-all]');
    const comandaCheckboxes = Array.from(document.querySelectorAll('[data-comanda-select]'));
    const comandaBulkDeleteBtn = document.querySelector('[data-comanda-bulk-delete]');
    const comandaBulkDeleteForm = document.getElementById('comenzi-bulk-delete-form');
    const comandaBulkInputsWrap = document.querySelector('[data-comanda-bulk-inputs]');
    const confirmWithModal = (options) => window.AppConfirm.confirm(options);

    const selectedComandaIds = () => comandaCheckboxes.filter((cb) => cb.checked).map((cb) => cb.value);

    const syncComandaSelectState = () => {
        if (!comandaBulkDeleteBtn || comandaCheckboxes.length === 0) {
            return;
        }

        const selectedCount = selectedComandaIds().length;
        comandaBulkDeleteBtn.disabled = selectedCount === 0;

        if (selectAllComenzi) {
            selectAllComenzi.checked = selectedCount === comandaCheckboxes.length;
            selectAllComenzi.indeterminate = selectedCount > 0 && selectedCount < comandaCheckboxes.length;
        }
    };

    if (selectAllComenzi) {
        selectAllComenzi.addEventListener('change', () => {
            comandaCheckboxes.forEach((cb) => {
                cb.checked = selectAllComenzi.checked;
            });
            syncComandaSelectState();
        });
    }

    comandaCheckboxes.forEach((cb) => cb.addEventListener('change', syncComandaSelectState));

    if (comandaBulkDeleteBtn && comandaBulkDeleteForm && comandaBulkInputsWrap) {
        comandaBulkDeleteBtn.addEventListener('click', async () => {
            const selected = selectedComandaIds();
            if (selected.length === 0) {
                return;
            }

            const message = selected.length === 1
                ? 'Esti de acord sa stergi comanda selectata? Va fi mutata in trash.'
                : 'Esti de acord sa stergi comenzile selectate? Acestea vor fi mutate in trash.';

            const confirmed = await confirmWithModal({
                title: 'Confirmare stergere',
                message,
                confirmText: 'Sterge',
                confirmClass: 'btn-danger',
            });
            if (!confirmed) {
                return;
            }

            comandaBulkInputsWrap.innerHTML = '';
            selected.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'comanda_ids[]';
                input.value = id;
                comandaBulkInputsWrap.appendChild(input);
            });

            comandaBulkDeleteForm.submit();
        });
    }

    syncComandaSelectState();
</script>
@endsection

