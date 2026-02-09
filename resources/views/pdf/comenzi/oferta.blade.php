<!doctype html>
<html lang="ro">
    <head>
        <meta charset="utf-8">
        <title>Ofertă comandă #{{ $comanda->id }}</title>
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
                background: linear-gradient(to right, #e11d48, #2563eb);
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
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12px;
            }
            th, td {
                border: 1px solid #e5e7eb;
                padding: 6px 8px;
                text-align: left;
            }
            th {
                background-color: #f3f4f6;
                font-weight: 600;
            }
            .text-right {
                text-align: right;
            }
            .total-row td {
                font-weight: 700;
            }
            .pdf-footer {
                position: fixed;
                bottom: 12px;
                left: 28px;
                right: 28px;
                text-align: center;
                font-size: 11px;
                color: #6b7280;
            }
        </style>
    </head>
    <body>
        @include('pdf.partials.header')

        <h1>Ofertă comandă #{{ $comanda->id }}</h1>

        <div class="meta-grid">
            <div class="meta-col">
                <div class="meta-item"><span class="meta-label">Client:</span> {{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Telefon:</span> {{ optional($comanda->client)->telefon ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Telefon secundar:</span> {{ optional($comanda->client)->telefon_secundar ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Email:</span> {{ optional($comanda->client)->email ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Adresă facturare:</span> {{ $comanda->adresa_facturare ?? optional($comanda->client)->adresa ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Adresă livrare:</span> {{ $comanda->adresa_livrare ?? $comanda->adresa_facturare ?? optional($comanda->client)->adresa ?? '-' }}</div>
            </div>
            <div class="meta-col">
                <div class="meta-item"><span class="meta-label">Tip:</span> {{ \App\Enums\TipComanda::options()[$comanda->tip] ?? $comanda->tip }}</div>
                <div class="meta-item"><span class="meta-label">Sursă:</span> {{ \App\Enums\SursaComanda::options()[$comanda->sursa] ?? $comanda->sursa }}</div>
                <div class="meta-item"><span class="meta-label">Status:</span> {{ \App\Enums\StatusComanda::options()[$comanda->status] ?? $comanda->status }}</div>
                <div class="meta-item"><span class="meta-label">Livrare:</span> {{ optional($comanda->timp_estimat_livrare)->format('d.m.Y H:i') ?? '-' }}</div>
            </div>
        </div>

        
        <div class="section-title">Detalii comanda</div>
        <div class="meta-item"><span class="meta-label">Necesita mockup:</span> {{ $comanda->necesita_mockup ? 'Da' : 'Nu' }}</div>
        <div class="meta-item"><span class="meta-label">Necesita tipar exemplu:</span> {{ $comanda->necesita_tipar_exemplu ? 'Da' : 'Nu' }}</div>

        <div class="section-title">Informatii comanda</div>
        @if ($comanda->solicitari->isEmpty())
            <div class="meta-item">Nu exista solicitari adaugate.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Solicitare client</th>
                        <th class="text-right">Cantitate</th>
                        <th>Adaugat de</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comanda->solicitari as $solicitare)
                        <tr>
                            <td>{{ $solicitare->solicitare_client ?? '-' }}</td>
                            <td class="text-right">{{ $solicitare->cantitate ?? '-' }}</td>
                            <td>{{ optional($solicitare->createdBy)->name ?? $solicitare->created_by_label ?? '-' }}</td>
                            <td>{{ optional($solicitare->created_at)->format('d.m.Y H:i') ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="section-title">Necesar</div>
        <table>
            <thead>
                <tr>
                    <th>Produs</th>
                    <th class="text-right">Cantitate</th>
                    <th class="text-right">Preț unitar</th>
                    <th class="text-right">Total linie</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($comanda->produse as $linie)
                    <tr>
                        <td>{{ $linie->custom_denumire ?? ($linie->produs->denumire ?? '-') }}</td>
                        <td class="text-right">{{ $linie->cantitate }}</td>
                        <td class="text-right">{{ number_format($linie->pret_unitar, 2) }}</td>
                        <td class="text-right">{{ number_format($linie->total_linie, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Nu există produse adăugate.</td>
                    </tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="3" class="text-right">Total</td>
                    <td class="text-right">{{ number_format($comanda->total, 2) }}</td>
                </tr>
            </tbody>
        </table>

        @include('pdf.partials.footer')
    </body>
</html>
