@php
    $gdprMediaMarketing = $gdprMediaMarketing ?? false;
    $gdprMethod = $gdprMethod ?? null;
    $isGdprPhysicalSource = $isGdprPhysicalSource ?? ($comanda->sursa === \App\Enums\SursaComanda::Fizic->value);
    $gdprActionLabel = $isGdprPhysicalSource ? 'Semneaza GDPR' : 'Inregistreaza GDPR';
    $gdprActionIcon = $isGdprPhysicalSource ? 'fa-pen-nib' : 'fa-clipboard-check';
    $gdprStatusPrefix = $gdprMethod === 'checkbox' ? 'GDPR acceptat implicit la' : 'GDPR semnat la';
@endphp

<div class="d-flex flex-wrap gap-2 mt-3">
    @if ($canWriteComenzi)
        <button
            type="button"
            class="btn btn-sm btn-outline-success"
            data-bs-toggle="modal"
            data-bs-target="#gdpr-modal"
        >
            <i class="fa-solid {{ $gdprActionIcon }} me-1"></i> {{ $gdprActionLabel }}
        </button>
    @endif
    <a
        class="btn btn-sm btn-outline-success {{ $gdprHasConsent ? '' : 'disabled' }}"
        href="{{ $gdprHasConsent ? route('comenzi.pdf.gdpr', $comanda) : '#' }}"
        aria-disabled="{{ $gdprHasConsent ? 'false' : 'true' }}"
        tabindex="{{ $gdprHasConsent ? '0' : '-1' }}"
    >
        <i class="fa-solid fa-file-shield me-1"></i> Descarca GDPR
    </a>
    <button
        type="button"
        class="btn btn-sm btn-outline-success"
        data-bs-toggle="modal"
        data-bs-target="#gdpr-email-modal"
        {{ $canSendGdprEmailEnabled ? '' : 'disabled' }}
    >
        <i class="fa-solid fa-paper-plane me-1"></i> Trimite GDPR pe e-mail
    </button>
</div>
<div class="small text-muted mt-2">
    @if ($gdprHasConsent)
        {{ $gdprStatusPrefix }} {{ $gdprSignedLabel }}.
        Informari produse/servicii: {{ $gdprMarketing ? 'Da' : 'Nu' }}.
        Foto/video marketing: {{ $gdprMediaMarketing ? 'Da' : 'Nu' }}.
    @else
        Nu exista un acord GDPR inregistrat.
    @endif
</div>
@if (!$clientEmail)
    <div class="text-muted small mt-2">Clientul nu are email setat pentru trimiterea documentelor.</div>
@endif
