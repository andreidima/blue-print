@extends ('layouts.app')

@section('content')
@php
    $client = $comanda->client;
    $totalCount = $emailEntries->count();
@endphp
<div class="mx-3 px-3 card" style="border-radius: 40px 40px 40px 40px;">
    <div class="row card-header align-items-center" style="border-radius: 40px 40px 0px 0px;">
        <div class="col-lg-8">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-envelope-open-text me-1"></i> Emailuri trimise comanda #{{ $comanda->id }}
            </span>
            @if ($client)
                <span class="badge bg-secondary">{{ $client->nume_complet }}</span>
            @endif
        </div>
        <div class="col-lg-4 text-end">
            <a class="btn btn-sm btn-secondary text-white border border-dark rounded-3" href="{{ Session::get('returnUrl', route('comenzi.email.show', $comanda)) }}">
                <i class="fa-solid fa-arrow-left me-1"></i> Inapoi
            </a>
        </div>
    </div>

    <div class="card-body px-3 py-4">
        @include ('errors.errors')

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Istoric emailuri trimise</div>
            <span class="badge bg-secondary">{{ $totalCount }}</span>
        </div>
        @forelse ($emailEntries as $email)
            <div class="border rounded-3 p-2 mb-2">
                <div class="row g-2 align-items-start">
                    <div class="{{ !empty($email['attachments']) ? 'col-lg-7 col-xl-8' : 'col-12' }}">
                        <div class="small text-muted">
                            {{ optional($email['created_at'])->format('d.m.Y H:i') }}
                            - {{ $email['recipient'] ?: '-' }}
                            @if (!empty($email['sent_by']))
                                - {{ $email['sent_by'] }}
                            @endif
                        </div>
                        <div class="fw-semibold">
                            <span class="badge bg-light text-dark border me-1">{{ $email['label'] }}</span>
                            {{ $email['subject'] ?: 'Fara subiect' }}
                        </div>
                        <div class="small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($email['body'] ?? ''), 180) }}</div>
                        @if (!empty(data_get($email, 'meta.documents', [])))
                            <div class="small text-muted">
                                Documente:
                                {{ collect(data_get($email, 'meta.documents', []))->map(fn ($item) => strtoupper((string) $item))->implode(', ') }}
                            </div>
                        @elseif (!empty($email['meta']['document']) && $email['meta']['document'] !== 'none')
                            <div class="small text-muted">Document: {{ strtoupper($email['meta']['document']) }}</div>
                        @endif
                    </div>
                    @if (!empty($email['attachments']))
                        <div class="col-lg-5 col-xl-4">
                            <div class="border rounded-3 bg-light px-2 py-1">
                                @foreach ($email['attachments'] as $attachment)
                                    <div class="d-flex justify-content-between align-items-start gap-2 py-1 {{ $loop->last ? '' : 'border-bottom' }}">
                                        <div class="small flex-grow-1" style="min-width: 0;">
                                            <div class="fw-semibold text-break">{{ $attachment['original_name'] ?? '-' }}</div>
                                            @if (!empty($attachment['label']))
                                                <div class="text-muted">{{ $attachment['label'] }}</div>
                                            @endif
                                        </div>
                                        <div class="d-flex flex-column gap-1 flex-shrink-0">
                                            @if (!empty($attachment['available']))
                                                <a
                                                    href="{{ $attachment['view_url'] }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="btn btn-sm btn-outline-primary py-0 px-2"
                                                >
                                                    <i class="fa-regular fa-eye me-1"></i> Vezi
                                                </a>
                                                <a
                                                    href="{{ $attachment['download_url'] }}"
                                                    class="btn btn-sm btn-outline-success py-0 px-2"
                                                >
                                                    <i class="fa-solid fa-download me-1"></i> Descarca
                                                </a>
                                            @else
                                                <span class="small text-muted">Indisponibil</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-muted small">Nu s-au trimis emailuri.</div>
        @endforelse
    </div>
</div>
@endsection
