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
                @if (!empty($email['meta']['pdf_name']))
                    <div class="small text-muted">Fisier: {{ $email['meta']['pdf_name'] }}</div>
                @endif
                @if (!empty($email['meta']['facturi']))
                    <div class="small text-muted">
                        Fisiere:
                        {{ collect($email['meta']['facturi'])->pluck('original_name')->filter()->implode(', ') ?: '-' }}
                    </div>
                @endif
                @if (!empty($email['meta']['document']) && $email['meta']['document'] !== 'none')
                    <div class="small text-muted">Document: {{ strtoupper($email['meta']['document']) }}</div>
                @endif
            </div>
        @empty
            <div class="text-muted small">Nu s-au trimis emailuri.</div>
        @endforelse
    </div>
</div>
@endsection
