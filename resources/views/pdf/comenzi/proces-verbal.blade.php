<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Proces verbal predare comanda #{{ $comanda->id }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .page {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            page-break-after: always;
        }
        .page:last-child { page-break-after: auto; }
        .page-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 210mm;
            height: 297mm;
            z-index: 0;
        }
        .content {
            position: relative;
            z-index: 1;
            padding: 38mm 14mm 24mm;
        }
        .title {
            font-size: 15px;
            font-weight: 700;
            color: #1f4e79;
            margin-bottom: 6px;
        }
        .label { font-weight: 700; }
        .row { margin: 2px 0; }
        .clearfix::after {
            content: "";
            display: block;
            clear: both;
        }
        .box {
            border: 1px solid #999;
            padding: 6px;
            margin-top: 6px;
        }
        table.table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.table th,
        table.table td {
            border: 1px solid #999;
            padding: 4px;
            vertical-align: top;
        }
        table.table th {
            background: #efefef;
            font-size: 10px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .totals {
            width: 45%;
            margin-left: auto;
            margin-top: 8px;
            border-collapse: collapse;
        }
        .totals td {
            border: 1px solid #999;
            padding: 5px;
        }
        .totals .label-cell {
            background: #f7f7f7;
            font-weight: 700;
        }
        .signature-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }
        .signature-grid td {
            width: 50%;
            vertical-align: top;
            padding: 6px;
        }
        .signature-box {
            border-top: 1px solid #444;
            margin-top: 22px;
            padding-top: 4px;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>
<body>
@php
    $orderNumber = str_pad((string) $comanda->id, 6, '0', STR_PAD_LEFT);
    $dataPredare = $comanda->finalizat_la ?? now();
    $lines = $comanda->produse->values();
    $linesPage1 = $lines->take(12);
    $linesPage2 = $lines->slice(12)->values();

    $toFileUrl = static function (string $path): string {
        return 'file:///' . ltrim(str_replace('\\', '/', $path), '/');
    };

    $bgPage1 = $toFileUrl(public_path('assets/pdf-backgrounds/pv-p1.jpg'));
    $bgPage2 = $toFileUrl(public_path('assets/pdf-backgrounds/pv-p2.jpg'));
@endphp

<section class="page">
    <img class="page-bg" src="{{ $bgPage1 }}" alt="">
    <div class="content">
        <div class="clearfix">
            <div style="float:left;">
                <div class="title">Proces verbal de predare</div>
            </div>
            <div style="float:right; text-align:right;">
                <div><span class="label">Nr.</span> {{ $orderNumber }}</div>
                <div><span class="label">Data:</span> {{ optional($dataPredare)->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
            </div>
        </div>

        <div class="box">
            <div class="row"><span class="label">Predator (operator):</span> ________________________</div>
            <div class="row"><span class="label">Catre client:</span> {{ optional($comanda->client)->nume_complet ?? '-' }}</div>
            <div class="row"><span class="label">Telefon client:</span> {{ optional($comanda->client)->telefon ?? '-' }}</div>
            <div class="row"><span class="label">Comanda / referinta:</span> {{ $orderNumber }}</div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width:6%;">Nr.</th>
                    <th style="width:48%;">Produs / serviciu predat</th>
                    <th style="width:12%;">Cant.</th>
                    <th style="width:16%;">Pret unitar</th>
                    <th style="width:18%;">Valoare</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linesPage1 as $linie)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td>
                            {{ $linie->custom_denumire ?? ($linie->produs->denumire ?? '-') }}
                            @if ($linie->descriere)
                                <div style="font-size:10px; color:#666;">{{ $linie->descriere }}</div>
                            @endif
                        </td>
                        <td class="text-right">{{ $linie->cantitate }}</td>
                        <td class="text-right">{{ number_format((float) $linie->pret_unitar, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $linie->total_linie, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Nu exista produse adaugate.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="page">
    <img class="page-bg" src="{{ $bgPage2 }}" alt="">
    <div class="content">
        <div class="title" style="font-size:14px;">Proces verbal de predare - continuare</div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width:6%;">Nr.</th>
                    <th style="width:48%;">Produs / serviciu predat</th>
                    <th style="width:12%;">Cant.</th>
                    <th style="width:16%;">Pret unitar</th>
                    <th style="width:18%;">Valoare</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linesPage2 as $linie)
                    <tr>
                        <td class="text-center">{{ $loop->iteration + 12 }}</td>
                        <td>{{ $linie->custom_denumire ?? ($linie->produs->denumire ?? '-') }}</td>
                        <td class="text-right">{{ $linie->cantitate }}</td>
                        <td class="text-right">{{ number_format((float) $linie->pret_unitar, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $linie->total_linie, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Nu exista alte pozitii.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="label-cell">Valoare totala predata</td>
                <td class="text-right">{{ number_format((float) $comanda->total, 2) }}</td>
            </tr>
            <tr>
                <td class="label-cell">Achitat</td>
                <td class="text-right">{{ (float) $comanda->total_platit > 0 ? 'Da' : 'Nu' }}</td>
            </tr>
            <tr>
                <td class="label-cell">Rest de plata</td>
                <td class="text-right">{{ number_format((float) $comanda->total - (float) $comanda->total_platit, 2) }}</td>
            </tr>
        </table>

        <div class="box" style="margin-top:12px;">
            Subsemnatii confirmam predarea / primirea produselor si serviciilor mentionate mai sus.
        </div>

        <table class="signature-grid">
            <tr>
                <td>
                    <div class="row"><span class="label">Predat de:</span> ________________________</div>
                    <div class="signature-box">Semnatura operator</div>
                </td>
                <td>
                    <div class="row"><span class="label">Primit de:</span> {{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                    <div class="signature-box">Semnatura client</div>
                </td>
            </tr>
        </table>
    </div>
</section>
</body>
</html>
