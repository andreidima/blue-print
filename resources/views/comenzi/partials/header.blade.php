@php
    $currentUser = auth()->user();
    $canViewFacturi = $canViewFacturi ?? $comanda->canViewFacturi($currentUser);
    $canOpenFacturaEmailModal = $canOpenFacturaEmailModal ?? $canViewFacturi;
    $smsCount = isset($comanda->sms_messages_count)
        ? (int) $comanda->sms_messages_count
        : ($comanda->relationLoaded('smsMessages') ? $comanda->smsMessages->count() : $comanda->smsMessages()->count());
    $ofertaEmailsCount = isset($comanda->oferta_emails_count)
        ? (int) $comanda->oferta_emails_count
        : ($comanda->relationLoaded('ofertaEmails') ? $comanda->ofertaEmails->count() : $comanda->ofertaEmails()->count());
    $facturaEmailsCount = isset($comanda->factura_emails_count)
        ? (int) $comanda->factura_emails_count
        : ($comanda->relationLoaded('facturaEmails') ? $comanda->facturaEmails->count() : $comanda->facturaEmails()->count());
    $emailLogsCount = isset($comanda->email_logs_count)
        ? (int) $comanda->email_logs_count
        : ($comanda->relationLoaded('emailLogs') ? $comanda->emailLogs->count() : $comanda->emailLogs()->count());
    $emailCount = $ofertaEmailsCount + $facturaEmailsCount + $emailLogsCount;
@endphp
<div class="row card-header align-items-center comanda-header">
    <div class="col-lg-8">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
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
            <div class="d-flex flex-wrap align-items-center gap-1">
                <a
                    class="btn p-0 border-0 bg-transparent"
                    href="{{ route('comenzi.sms.show', $comanda) }}"
                    aria-label="SMS comanda"
                    title="SMS comanda"
                >
                    <span class="badge bg-info text-dark">
                        <i class="fa-solid fa-comment-sms me-1"></i>{{ $smsCount }}
                    </span>
                </a>
                <a
                    class="btn p-0 border-0 bg-transparent"
                    href="{{ route('comenzi.email.show', $comanda) }}"
                    aria-label="Email comanda"
                    title="Email comanda"
                >
                    <span class="badge bg-secondary">
                        <i class="fa-solid fa-envelope me-1"></i>{{ $emailCount }}
                    </span>
                </a>
                @if ($canViewFacturi)
                    <button
                        type="button"
                        class="btn p-0 border-0 bg-transparent"
                        data-bs-toggle="modal"
                        data-bs-target="#factura-email-modal"
                        aria-label="Trimite email factura"
                        title="Trimite email factura"
                        {{ $canOpenFacturaEmailModal ? '' : 'disabled' }}
                    >
                        <span class="badge bg-secondary">
                            <i class="fa-solid fa-paper-plane me-1"></i>{{ $facturaEmailsCount }}
                        </span>
                    </button>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4 text-end">
        @if ($canWriteComenzi)
            <form method="POST" action="{{ route('comenzi.duplicate', $comanda) }}" class="d-inline" data-confirm="Sigur vrei sa duplici aceasta comanda?">
                @csrf
                <button type="submit" class="btn btn-sm btn-warning text-dark rounded-3 shadow-sm me-2">
                    <i class="fa-solid fa-copy me-1"></i> Duplicare
                </button>
            </form>
            <form method="POST" action="{{ route('comenzi.destroy', $comanda) }}" class="d-inline" data-confirm="Sigur vrei sa stergi aceasta comanda? Va fi mutata in trash.">
                @method('DELETE')
                @csrf
                <button type="submit" class="btn btn-sm btn-danger text-white rounded-3 shadow-sm me-2">
                    <i class="fa-solid fa-trash me-1"></i> Sterge
                </button>
            </form>
        @endif
        <a class="btn btn-sm btn-outline-light rounded-3 shadow-sm" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
            <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
        </a>
    </div>
</div>
