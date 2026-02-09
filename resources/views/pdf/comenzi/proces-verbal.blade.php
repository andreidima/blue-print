<!doctype html>
<html lang="ro">
    <head>
        <meta charset="utf-8">
        <title>Proces verbal predare comanda #{{ $comanda->id }}</title>
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
                background: linear-gradient(to right, #0f766e, #f97316);
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
            .signature-grid {
                display: table;
                width: 100%;
                margin-top: 22px;
            }
            .signature-col {
                display: table-cell;
                width: 50%;
                padding-right: 12px;
                vertical-align: top;
            }
            .signature-box {
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                padding: 12px;
                min-height: 90px;
            }
            .signature-label {
                font-weight: 600;
                margin-bottom: 6px;
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
        @php
            $orderNumber = str_pad((string) $comanda->id, 6, '0', STR_PAD_LEFT);
            $dataPredare = $comanda->finalizat_la ?? now();
        @endphp

        @include('pdf.partials.header')

        <h1>Proces verbal predare comanda #{{ $orderNumber }}</h1>

        <div class="meta-grid">
            <div class="meta-col">
                <div class="meta-item"><span class="meta-label">Client:</span> {{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Telefon:</span> {{ optional($comanda->client)->telefon ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Telefon secundar:</span> {{ optional($comanda->client)->telefon_secundar ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Email:</span> {{ optional($comanda->client)->email ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Adresa livrare:</span> {{ $comanda->adresa_livrare ?? $comanda->adresa_facturare ?? optional($comanda->client)->adresa ?? '-' }}</div>
            </div>
            <div class="meta-col">
                <div class="meta-item"><span class="meta-label">Tip:</span> {{ \App\Enums\TipComanda::options()[$comanda->tip] ?? $comanda->tip }}</div>
                <div class="meta-item"><span class="meta-label">Status:</span> {{ \App\Enums\StatusComanda::options()[$comanda->status] ?? $comanda->status }}</div>
                <div class="meta-item"><span class="meta-label">Data solicitarii:</span> {{ optional($comanda->data_solicitarii)->format('d.m.Y') ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Livrare estimata:</span> {{ optional($comanda->timp_estimat_livrare)->format('d.m.Y H:i') ?? '-' }}</div>
                <div class="meta-item"><span class="meta-label">Data predare:</span> {{ optional($dataPredare)->format('d.m.Y H:i') ?? '-' }}</div>
            </div>
        </div>

        <div class="section-title">Produse predate</div>
        <table>
            <thead>
                <tr>
                    <th>Produs</th>
                    <th class="text-right">Cantitate</th>
                    <th class="text-right">Pret unitar</th>
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
                        <td colspan="4">Nu exista produse adaugate.</td>
                    </tr>
                @endforelse
                @if ($comanda->produse->isNotEmpty())
                    <tr>
                        <td colspan="3" class="text-right"><strong>Total</strong></td>
                        <td class="text-right"><strong>{{ number_format($comanda->total, 2) }}</strong></td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="section-title">Observatii</div>
        <div class="meta-item">Prin prezentul proces verbal, produsele mentionate mai sus au fost predate catre client.</div>

        <div class="signature-grid">
            <div class="signature-col">
                <div class="signature-box">
                    <div class="signature-label">Predat de (Tipografie)</div>
                    <div>Semnatura:</div>
                    <div style="margin-top:24px;">Nume:</div>
                </div>
            </div>
            <div class="signature-col">
                <div class="signature-box">
                    <div class="signature-label">Preluat de (Client)</div>
                    <div>Semnatura:</div>
                    <div style="margin-top:24px;">Nume:</div>
                </div>
            </div>
        </div>

        @include('pdf.partials.footer')
    </body>
</html>
