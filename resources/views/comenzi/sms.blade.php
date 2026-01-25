@extends ('layouts.app')

@section('content')
@php
    $client = $comanda->client;
    $smsCount = $smsMessages->count();
    $templateBodies = $smsTemplates->mapWithKeys(fn ($template) => [$template->id => $template->body])->all();
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-8">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-comment-sms me-1"></i> SMS comanda #{{ $comanda->id }}
            </span>
            @if ($client)
                <span class="badge bg-secondary">{{ $client->nume_complet }}</span>
            @endif
        </div>
        <div class="col-lg-4 text-end">
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('comenzi.index')) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        <div class="row g-4">
            <div class="col-lg-7">
                <form method="POST" action="{{ route('comenzi.sms.send', $comanda) }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label mb-1">Template</label>
                        <select class="form-select rounded-3" name="template_id" id="template_id">
                            @foreach ($smsTemplates as $template)
                                <option value="{{ $template->id }}" {{ (string) old('template_id', $defaultTemplateId) === (string) $template->id ? 'selected' : '' }}>
                                    {{ $template->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="small text-muted mt-1">
                            Template-urile se pot edita din
                            <a href="{{ route('sms-templates.index') }}">managerul de template-uri</a>.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">Telefon</label>
                        <input
                            type="text"
                            class="form-control rounded-3"
                            name="recipients"
                            value="{{ old('recipients', $clientTelefon) }}"
                            placeholder="0722..., 0744..., +407..."
                            required
                        >
                        <div class="small text-muted mt-1">Poti introduce mai multe numere separate prin virgula.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">Mesaj</label>
                        <textarea name="message" id="message" class="form-control rounded-3" rows="6" required>{{ old('message', $defaultMessage) }}</textarea>
                        <div class="small text-muted mt-1">Mesajul poate fi modificat inainte de trimitere.</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('sms-templates.index') }}">
                            <i class="fa-solid fa-gear me-1"></i> Template-uri
                        </a>
                        <button type="submit" class="btn btn-primary text-white">
                            <i class="fa-solid fa-paper-plane me-1"></i> Trimite SMS
                        </button>
                    </div>
                </form>
            </div>
            <div class="col-lg-5">
                <div class="border rounded-3 p-3 bg-light">
                    <div class="fw-semibold mb-2">Date client</div>
                    <div class="small text-muted">Nume</div>
                    <div>{{ $client?->nume_complet ?? '-' }}</div>
                    <div class="small text-muted mt-2">Telefon</div>
                    <div>{{ $client?->telefon ?? '-' }}</div>
                    <div class="small text-muted mt-2">Email</div>
                    <div>{{ $client?->email ?? '-' }}</div>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Istoric SMS</div>
            <span class="badge bg-secondary">{{ $smsCount }}</span>
        </div>
        @forelse ($smsMessages as $sms)
            <div class="border rounded-3 p-2 mb-2">
                <div class="small text-muted">
                    {{ optional($sms->sent_at ?? $sms->created_at)->format('d.m.Y H:i') }}
                    - {{ $sms->recipient }}
                    @if ($sms->sentBy)
                        - {{ $sms->sentBy->name }}
                    @endif
                </div>
                <div class="fw-semibold">
                    {{ $sms->template?->name ?? 'SMS' }}
                    <span class="badge {{ $sms->status === 'sent' ? 'bg-success' : 'bg-danger' }}">
                        {{ $sms->status === 'sent' ? 'Trimis' : 'Esuat' }}
                    </span>
                </div>
                <div class="small">{{ $sms->message }}</div>
                @if ($sms->status !== 'sent')
                    <div class="small text-danger">{{ $sms->gateway_message ?? 'Eroare necunoscuta' }}</div>
                @endif
            </div>
        @empty
            <div class="text-muted small">Nu s-au trimis SMS-uri.</div>
        @endforelse
    </div>
</div>

<script>
    const smsTemplateBodies = @json($templateBodies);
    const smsPlaceholders = @json($placeholders);
    const templateSelect = document.getElementById('template_id');
    const messageField = document.getElementById('message');

    const applyPlaceholders = (text) => {
        let output = text;
        Object.entries(smsPlaceholders).forEach(([key, value]) => {
            output = output.split(key).join(value ?? '');
        });
        return output;
    };

    const updateMessageFromTemplate = () => {
        const templateBody = smsTemplateBodies[templateSelect.value] || '';
        messageField.value = applyPlaceholders(templateBody);
    };

    templateSelect?.addEventListener('change', updateMessageFromTemplate);
</script>
@endsection
