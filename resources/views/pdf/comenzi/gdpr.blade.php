<!doctype html>
<html lang="ro">
    <head>
        <meta charset="utf-8">
        <title>Acord GDPR comanda #{{ $comanda->id }}</title>
        <style>
            @page {
                margin: 24px 28px;
            }
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
                color: #111827;
                padding-bottom: 52px;
            }
            .pdf-header {
                text-align: center;
                margin-bottom: 12px;
            }
            .pdf-header img {
                max-width: 360px;
                max-height: 140px;
            }
            .pdf-logo-fallback {
                font-size: 18px;
                font-weight: 600;
            }
            .pdf-divider {
                height: 2px;
                border: 0;
                background: linear-gradient(to right, #0ea5e9, #22c55e);
                margin: 8px 0 20px;
            }
            h1 {
                font-size: 20px;
                margin: 0 0 12px;
            }
            .meta-grid {
                display: table;
                width: 100%;
                margin-bottom: 16px;
            }
            .meta-col {
                display: table-cell;
                width: 50%;
                vertical-align: top;
                padding-right: 12px;
            }
            .meta-item {
                margin-bottom: 6px;
            }
            .meta-label {
                font-weight: 600;
            }
            .section-title {
                font-size: 14px;
                font-weight: 700;
                margin: 18px 0 8px;
            }
            .consent-box {
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                padding: 12px;
                background-color: #f8fafc;
            }
            .signature-box {
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                padding: 12px;
                min-height: 120px;
                margin-top: 8px;
            }
            .signature-label {
                font-weight: 600;
                margin-bottom: 6px;
            }
            .signature-img {
                max-width: 100%;
                height: auto;
            }
            .pdf-footer {
                position: fixed;
                bottom: 16px;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 10px;
                color: #6b7280;
            }
        </style>
    </head>
    <body>
        @php
            $clientSnapshot = $consent->client_snapshot ?? [];
            $clientName = $clientSnapshot['nume'] ?? optional($comanda->client)->nume_complet ?? '-';
            $clientType = $clientSnapshot['type'] ?? optional($comanda->client)->type ?? 'pf';
            $clientAddress = $clientSnapshot['adresa'] ?? optional($comanda->client)->adresa ?? '-';
            $clientEmail = $clientSnapshot['email'] ?? optional($comanda->client)->email ?? '-';
            $clientPhone = $clientSnapshot['telefon'] ?? optional($comanda->client)->telefon ?? '-';
            $clientPhoneSecondary = $clientSnapshot['telefon_secundar'] ?? optional($comanda->client)->telefon_secundar ?? '-';
            $clientCnp = $clientSnapshot['cnp'] ?? optional($comanda->client)->cnp ?? null;
            $clientCui = $clientSnapshot['cui'] ?? optional($comanda->client)->cui ?? null;
            $clientRegCom = $clientSnapshot['reg_com'] ?? optional($comanda->client)->reg_com ?? null;
            $clientReprezentant = $clientSnapshot['reprezentant'] ?? optional($comanda->client)->reprezentant ?? null;
            $clientReprezentantFunctie = $clientSnapshot['reprezentant_functie'] ?? optional($comanda->client)->reprezentant_functie ?? null;
            $signedAt = $consent->signed_at ?? $consent->created_at;
            $signedLabel = $signedAt ? $signedAt->format('d.m.Y H:i') : '-';
            $signatureSrc = '';
            if ($consent->signature_path) {
                $signaturePath = \Illuminate\Support\Facades\Storage::disk('public')->path($consent->signature_path);
                if (is_file($signaturePath)) {
                    $signatureBinary = file_get_contents($signaturePath);
                    if ($signatureBinary !== false) {
                        $signatureSrc = 'data:image/png;base64,' . base64_encode($signatureBinary);
                    }
                }
            }
        @endphp

        @include('pdf.partials.header')

        <h1>Acord GDPR</h1>

        <div class="meta-grid">
            <div class="meta-col">
                <div class="meta-item"><span class="meta-label">Comanda:</span> #{{ $comanda->id }}</div>
                <div class="meta-item"><span class="meta-label">Data semnarii:</span> {{ $signedLabel }}</div>
            </div>
            <div class="meta-col">
                <div class="meta-item"><span class="meta-label">Client:</span> {{ $clientName }}</div>
                <div class="meta-item"><span class="meta-label">Tip client:</span> {{ strtoupper($clientType) }}</div>
            </div>
        </div>

        <div class="section-title">Date de contact</div>
        <div class="consent-box">
            <div class="meta-item"><span class="meta-label">Adresa:</span> {{ $clientAddress }}</div>
            <div class="meta-item"><span class="meta-label">Email:</span> {{ $clientEmail }}</div>
            <div class="meta-item"><span class="meta-label">Telefon:</span> {{ $clientPhone }}</div>
            <div class="meta-item"><span class="meta-label">Telefon secundar:</span> {{ $clientPhoneSecondary }}</div>
            @if ($clientType === 'pf' && $clientCnp)
                <div class="meta-item"><span class="meta-label">CNP:</span> {{ $clientCnp }}</div>
            @endif
            @if ($clientType === 'pj')
                @if ($clientCui)
                    <div class="meta-item"><span class="meta-label">CUI:</span> {{ $clientCui }}</div>
                @endif
                @if ($clientRegCom)
                    <div class="meta-item"><span class="meta-label">Reg. Comertului:</span> {{ $clientRegCom }}</div>
                @endif
                @if ($clientReprezentant)
                    <div class="meta-item"><span class="meta-label">Reprezentant:</span> {{ $clientReprezentant }}</div>
                @endif
                @if ($clientReprezentantFunctie)
                    <div class="meta-item"><span class="meta-label">Functie:</span> {{ $clientReprezentantFunctie }}</div>
                @endif
            @endif
        </div>

        <div class="section-title">Consimtamant</div>
        <div class="consent-box">
            <div class="meta-item">
                Acord prelucrare date personale: <strong>{{ $consent->consent_processing ? 'DA' : 'NU' }}</strong>
            </div>
            <div class="meta-item">
                Acord marketing/promovare: <strong>{{ $consent->consent_marketing ? 'DA' : 'NU' }}</strong>
            </div>
            <div class="meta-item" style="margin-top:8px;">
                Semnatura este acordul privind prelucrarea datelor cu caracter personal si politica privind promovarea
                produselor si serviciilor in scop de marketing/promovare.
            </div>
        </div>

        <div class="section-title">Semnatura</div>
        <div class="signature-box">
            @if ($signatureSrc)
                <img class="signature-img" src="{{ $signatureSrc }}" alt="Semnatura">
            @else
                <div>Semnatura nu a fost colectata (confirmare prin bifare).</div>
            @endif
        </div>

        @include('pdf.partials.footer')
    </body>
</html>
