<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Oferta comerciala #{{ $comanda->id }}</title>
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
            margin-bottom: 4px;
        }
        .subtitle {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .clearfix::after {
            content: "";
            display: block;
            clear: both;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .meta-table td {
            vertical-align: top;
            padding: 2px 4px;
        }
        .row { margin: 2px 0; }
        .label { font-weight: 700; }
        .box {
            border: 1px solid #999;
            padding: 6px;
            margin-top: 6px;
        }
        .muted {
            color: #666;
            font-size: 10px;
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
            width: 46%;
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
    </style>
</head>
<body>
@php
    $orderNumber = str_pad((string) $comanda->id, 6, '0', STR_PAD_LEFT);
    $offerDate = optional($comanda->data_solicitarii)->format('d/m/Y') ?? now()->format('d/m/Y');
    $billingAddress = $comanda->adresa_facturare ?? optional($comanda->client)->adresa ?? '-';
    $deliveryAddress = $comanda->adresa_livrare ?? $billingAddress;
    $validityDays = null;
    if ($comanda->data_solicitarii && $comanda->valabilitate_oferta) {
        $validityDays = (int) $comanda->data_solicitarii->diffInDays($comanda->valabilitate_oferta, false);
        if ($validityDays < 0) {
            $validityDays = null;
        }
    }
    $estimatedDays = null;
    if ($comanda->data_solicitarii && $comanda->timp_estimat_livrare) {
        $estimatedDays = (int) $comanda->data_solicitarii->diffInDays($comanda->timp_estimat_livrare, false);
    }

    $products = $comanda->produse->values();
    $productsPage1 = $products->take(9);
    $productsNextPages = $products->slice(9)->values();
    $showSecondPage = $productsNextPages->isNotEmpty() || $comanda->solicitari->isNotEmpty();

    $toFileUrl = static function (string $path): string {
        return 'file:///' . ltrim(str_replace('\\', '/', $path), '/');
    };

    $bgPage1 = $toFileUrl(public_path('assets/pdf-backgrounds/oferta-p1.jpg'));
    $bgPage2 = $toFileUrl(public_path('assets/pdf-backgrounds/oferta-p2.jpg'));
@endphp

<section class="page">
    <img class="page-bg" src="{{ $bgPage1 }}" alt="">
    <div class="content">
        <div class="clearfix">
            <div style="float:left; width:70%;">
                <div class="title">Oferta comerciala</div>
                <div class="subtitle">Produse si servicii tipografice</div>
            </div>
            <div style="float:right; width:28%; text-align:right;">
                <div><span class="label">Nr.</span> {{ $orderNumber }}</div>
                <div><span class="label">Data:</span> {{ $offerDate }}</div>
            </div>
        </div>

        <table class="meta-table">
            <tr>
                <td style="width:60%;">
                    <div class="row"><span class="label">Client:</span> {{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                    <div class="row"><span class="label">Telefon:</span> {{ optional($comanda->client)->telefon ?? '-' }}</div>
                    <div class="row"><span class="label">Telefon secundar:</span> {{ optional($comanda->client)->telefon_secundar ?? '-' }}</div>
                    <div class="row"><span class="label">E-mail:</span> {{ optional($comanda->client)->email ?? '-' }}</div>
                    <div class="row"><span class="label">Adresa facturare:</span> {{ $billingAddress }}</div>
                    <div class="row"><span class="label">Adresa livrare:</span> {{ $deliveryAddress }}</div>
                </td>
                <td style="width:40%;">
                    <div class="row"><span class="label">Tip comanda:</span> {{ \App\Enums\TipComanda::options()[$comanda->tip] ?? $comanda->tip }}</div>
                    <div class="row"><span class="label">Sursa:</span> {{ \App\Enums\SursaComanda::options()[$comanda->sursa] ?? $comanda->sursa }}</div>
                    <div class="row"><span class="label">Status:</span> {{ \App\Enums\StatusComanda::options()[$comanda->status] ?? $comanda->status }}</div>
                    <div class="row"><span class="label">Valabilitate:</span> {{ $validityDays !== null ? $validityDays . ' zile' : (optional($comanda->valabilitate_oferta)->format('d.m.Y') ?? '-') }}</div>
                    <div class="row"><span class="label">Timp executie:</span> {{ $estimatedDays !== null ? $estimatedDays . ' zile' : (optional($comanda->timp_estimat_livrare)->format('d.m.Y H:i') ?? '-') }}</div>
                </td>
            </tr>
        </table>

        <div class="box">
            Va multumim pentru interesul acordat produselor si serviciilor tipografiei blu.e-print.
            Urmare a solicitarii, va transmitem oferta de pret.
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width:6%;">Nr.</th>
                    <th style="width:54%;">Denumire produs / serviciu</th>
                    <th style="width:12%;">Cant.</th>
                    <th style="width:14%;">Pret unitar</th>
                    <th style="width:14%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($productsPage1 as $linie)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td>
                            <strong>{{ $linie->custom_denumire ?? ($linie->produs->denumire ?? '-') }}</strong>
                            @if ($linie->descriere)
                                <div class="muted">{{ $linie->descriere }}</div>
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

        @unless ($showSecondPage)
            <table class="totals">
                <tr>
                    <td class="label-cell">Total comanda</td>
                    <td class="text-right"><strong>{{ number_format((float) $comanda->total, 2) }}</strong></td>
                </tr>
                <tr>
                    <td class="label-cell">Status plata</td>
                    <td class="text-right">{{ \App\Enums\StatusPlata::options()[$comanda->status_plata] ?? $comanda->status_plata }}</td>
                </tr>
            </table>
        @endunless
    </div>
</section>

@if ($showSecondPage)
<section class="page">
    <img class="page-bg" src="{{ $bgPage2 }}" alt="">
    <div class="content">
        <div class="title" style="font-size:14px;">Oferta comerciala - continuare</div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width:6%;">Nr.</th>
                    <th style="width:54%;">Denumire produs / serviciu</th>
                    <th style="width:12%;">Cant.</th>
                    <th style="width:14%;">Pret unitar</th>
                    <th style="width:14%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($productsNextPages as $linie)
                    <tr>
                        <td class="text-center">{{ $loop->iteration + $productsPage1->count() }}</td>
                        <td>
                            <strong>{{ $linie->custom_denumire ?? ($linie->produs->denumire ?? '-') }}</strong>
                            @if ($linie->descriere)
                                <div class="muted">{{ $linie->descriere }}</div>
                            @endif
                        </td>
                        <td class="text-right">{{ $linie->cantitate }}</td>
                        <td class="text-right">{{ number_format((float) $linie->pret_unitar, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $linie->total_linie, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Nu exista pozitii suplimentare.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="label-cell">Total comanda</td>
                <td class="text-right"><strong>{{ number_format((float) $comanda->total, 2) }}</strong></td>
            </tr>
            <tr>
                <td class="label-cell">Status plata</td>
                <td class="text-right">{{ \App\Enums\StatusPlata::options()[$comanda->status_plata] ?? $comanda->status_plata }}</td>
            </tr>
        </table>

        @if ($comanda->solicitari->isNotEmpty())
            <div class="box">
                <div class="label" style="margin-bottom:4px;">Informatii comanda</div>
                @foreach ($comanda->solicitari as $solicitare)
                    <div class="row">
                        <span class="label">{{ $loop->iteration }}.</span>
                        {{ $solicitare->solicitare_client ?? '-' }}
                        @if ($solicitare->cantitate)
                            (cantitate: {{ $solicitare->cantitate }})
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
</body>
</html>
