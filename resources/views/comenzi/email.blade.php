@extends ('layouts.app')

@section('content')
@php
    $client = $comanda->client;
    $canSendEmail = auth()->user()?->hasPermission('comenzi.email.send') ?? false;
    $templatePayload = $emailTemplates->mapWithKeys(fn ($template) => [
        $template->id => [
            'subject' => $template->subject,
            'body' => $template->body_html,
            'color' => $template->color,
        ],
    ])->all();
    $defaultTemplateColor = ($defaultTemplateId && isset($templatePayload[$defaultTemplateId]))
        ? $templatePayload[$defaultTemplateId]['color']
        : '#6c757d';
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-8">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-envelope me-1"></i> Email comanda #{{ $comanda->id }}
            </span>
            @if ($client)
                <span class="badge bg-secondary">{{ $client->nume_complet }}</span>
            @endif
        </div>
        <div class="col-lg-4 text-end">
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
            <a class="btn btn-sm btn-outline-primary rounded-3 ms-2" href="{{ route('comenzi.email.history', $comanda) }}">
                <i class="fa-solid fa-envelope-open-text me-1"></i> Emailuri trimise
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        <div class="row g-4">
            <div class="col-lg-7">
                <form method="POST" action="{{ route('comenzi.email.send', $comanda) }}" data-email-placeholders='@json($placeholders)'>
                    @csrf
                    <fieldset {{ $canSendEmail ? '' : 'disabled' }}>

                    <div class="mb-3">
                        <label class="form-label mb-1">Template</label>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select rounded-3" name="template_id" id="email_template_id" data-email-template-select>
                                <option value="">Fara template</option>
                                @foreach ($emailTemplates as $template)
                                    <option value="{{ $template->id }}" style="color: {{ $template->color ?? '#111827' }};" {{ (string) old('template_id', $defaultTemplateId) === (string) $template->id ? 'selected' : '' }}>
                                        {{ $template->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span id="email-template-color" class="rounded-circle d-inline-block" style="width:16px; height:16px; background-color: {{ $defaultTemplateColor }};"></span>
                        </div>
                        <div class="small text-muted mt-1">
                            Template-urile se pot edita din <a href="{{ route('email-templates.index') }}">managerul de template-uri</a>.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">Tip document</label>
                        <select class="form-select rounded-3" name="document_type" id="document_type">
                            <option value="none" {{ old('document_type') === 'none' ? 'selected' : '' }}>Fara document</option>
                            <option value="factura" {{ old('document_type') === 'factura' ? 'selected' : '' }}>Factura (fisiere incarcate)</option>
                            <option value="oferta" {{ old('document_type') === 'oferta' ? 'selected' : '' }}>Oferta (PDF generat)</option>
                            <option value="gdpr" {{ old('document_type') === 'gdpr' ? 'selected' : '' }}>GDPR (PDF generat)</option>
                        </select>
                        <div class="small text-muted mt-1">Butonul "Descarca documentul" se adauga automat in email.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">Subiect</label>
                        <input type="text" name="subject" class="form-control rounded-3" value="{{ old('subject', $defaultSubject) }}" required data-email-subject>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">Mesaj</label>
                        <input id="email_body" type="hidden" name="body" value="{{ old('body', $defaultBody) }}" data-email-body>
                        @include('partials.trix-toolbar-basic', ['toolbarId' => 'trix-toolbar-comenzi-email'])
                        <trix-editor input="email_body" toolbar="trix-toolbar-comenzi-email" class="bg-white rounded-3"></trix-editor>
                        <div class="small text-muted mt-1">Mesajul poate fi modificat inainte de trimitere.</div>
                    </div>

                    <div class="d-flex justify-content-end">
                        @if ($canSendEmail)
                            <button type="submit" class="btn btn-primary text-white">
                                <i class="fa-solid fa-paper-plane me-1"></i> Trimite email
                            </button>
                        @endif
                    </div>
                    </fieldset>
                </form>
            </div>
            <div class="col-lg-5">
                <div class="border rounded-3 p-3 bg-light">
                    <div class="fw-semibold mb-2">Date client</div>
                    <div class="small text-muted">Nume</div>
                    <div>{{ $client?->nume_complet ?? '-' }}</div>
                    <div class="small text-muted mt-2">Telefon</div>
                    <div>{{ $client?->telefon ?? '-' }}</div>
                    <div class="small text-muted mt-2">Telefon secundar</div>
                    <div>{{ $client?->telefon_secundar ?? '-' }}</div>
                    <div class="small text-muted mt-2">Email</div>
                    <div>{{ $client?->email ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>
<script>
    const emailTemplates = @json($templatePayload);
    const emailPlaceholders = @json($placeholders);
    const templateSelect = document.getElementById('email_template_id');
    const bodyInput = document.getElementById('email_body');
    const subjectInput = document.querySelector('[data-email-subject]');
    const colorPreview = document.getElementById('email-template-color');

    const applyEmailPlaceholders = (text) => {
        let output = text || '';
        Object.entries(emailPlaceholders || {}).forEach(([key, value]) => {
            output = output.split(key).join(value ?? '');
        });
        return output;
    };

    const updateEmailFromTemplate = () => {
        const template = emailTemplates[templateSelect.value] || null;
        if (!template) {
            if (colorPreview) colorPreview.style.backgroundColor = '#6c757d';
            return;
        }

        const subject = applyEmailPlaceholders(template.subject || '');
        const body = applyEmailPlaceholders(template.body || '');

        if (subjectInput) subjectInput.value = subject;
        if (bodyInput) bodyInput.value = body;
        if (colorPreview) colorPreview.style.backgroundColor = template.color || '#6c757d';

        const trixEditor = document.querySelector('trix-editor');
        if (trixEditor && trixEditor.editor) {
            trixEditor.editor.loadHTML(body);
        }
    };

    templateSelect?.addEventListener('change', updateEmailFromTemplate);
</script>
@endsection
