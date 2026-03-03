<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Ofertă comercială #{{ $comanda->id }}</title>
    <style>
        @page { margin: 35mm 14mm 24mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #27358f;
            margin: 0;
            padding: 0;
            position: relative;
        }
        .page-bg-fixed {
            position: fixed;
            top: -35mm;
            left: -14mm;
            width: 210mm;
            height: 297mm;
            z-index: -1000;
        }
        .page-bg-first {
            position: absolute;
            top: -35mm;
            left: -14mm;
            width: 210mm;
            height: 297mm;
            z-index: -999;
        }
        .content {
            position: relative;
            z-index: 1;
        }
        .doc-meta-title {
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            margin: 0 0 8px;
        }
        .page-number {
            position: fixed;
            z-index: 2;
            width: 9mm;
            height: 9mm;
            line-height: 9mm;
            text-align: center;
            color: #fff;
            font-size: 12px;
            font-weight: 900;
            left: 178.8mm;
            top: 222.5mm;
        }
        .page-number::after {
            content: counter(page);
            display: inline-block;
            font-family: DejaVu Sans, sans-serif;
            font-weight: 900;
            text-shadow:
                0.12mm 0 0 #fff,
                -0.12mm 0 0 #fff,
                0 0.12mm 0 #fff,
                0 -0.12mm 0 #fff;
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
            border: 0;
            padding: 6px;
            margin-top: 6px;
        }
        .muted {
            color: #27358f;
            font-size: 10px;
        }
        table.table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            color: #111827;
        }
        table.table th,
        table.table td {
            border: 1px solid #999;
            padding: 3px;
            vertical-align: top;
            color: #111827;
        }
        table.table tr {
            page-break-inside: avoid;
        }
        table.table .muted {
            color: #111827;
        }
        table.table th {
            font-size: 9px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .summary-row td {
            padding: 4px 5px;
        }
        .summary-label {
            text-align: right;
            font-weight: 700;
        }
        .summary-total td {
            font-size: 12px;
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
    $vatRate = 0.21;
    $vatRatePercentLabel = '21%';
    $subtotal = (float) $products->sum(fn ($item) => (float) $item->total_linie);
    $vatTotal = $subtotal * $vatRate;
    $totalWithVat = $subtotal + $vatTotal;
    $umDefault = 'buc';
    $hasPreviousClientOrders = $comanda->client_id
        ? \App\Models\Comanda::query()
            ->where('client_id', $comanda->client_id)
            ->where('id', '<', $comanda->id)
            ->exists()
        : false;
    $clientTypeLabel = $hasPreviousClientOrders ? 'client existent' : 'client nou';
    $clientDiscountLabel = '-';
    $bgPage1 = \App\Support\PdfAsset::fromPublic('assets/pdf-backgrounds/oferta-p1.jpg');
    $bgPage2 = \App\Support\PdfAsset::fromPublic('assets/pdf-backgrounds/oferta-p2.jpg');
    $showDetails = (bool) ($comanda->afiseaza_detalii ?? true);
    $formatQuantity = static function ($value): string {
        $formatted = number_format((float) $value, 4, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    };
@endphp

<img class="page-bg-fixed" src="{{ $bgPage2 }}" alt="">
<img class="page-bg-first" src="{{ $bgPage1 }}" alt="">
<div class="page-number" aria-hidden="true"></div>
<div class="content">
        <div class="doc-meta-title">Nr. {{ $orderNumber }} <br> Data: {{ $offerDate }}</div>

        <table class="meta-table">
            <tr>
                <td style="width:60%;">
                    <div class="row"><span class="label">Client:</span> {{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                    <div class="row"><span class="label">Telefon:</span> {{ optional($comanda->client)->telefon ?? '-' }}</div>
                    <div class="row"><span class="label">Telefon secundar:</span> {{ optional($comanda->client)->telefon_secundar ?? '-' }}</div>
                    <div class="row"><span class="label">E-mail:</span> {{ optional($comanda->client)->email ?? '-' }}</div>
                    <div class="row"><span class="label">Adresă facturare:</span> {{ $billingAddress }}</div>
                    <div class="row"><span class="label">Adresă livrare:</span> {{ $deliveryAddress }}</div>
                </td>
                <td style="width:40%;">
                    <div class="row"><span class="label">Tip comandă:</span> {{ \App\Enums\TipComanda::options()[$comanda->tip] ?? $comanda->tip }}</div>
                    <div class="row"><span class="label">Sursă:</span> {{ \App\Enums\SursaComanda::options()[$comanda->sursa] ?? $comanda->sursa }}</div>
                    <div class="row"><span class="label">Status:</span> {{ \App\Enums\StatusComanda::options()[$comanda->status] ?? $comanda->status }}</div>
                    <div class="row"><span class="label">Tip client:</span> {{ $clientTypeLabel }}</div>
                    <div class="row"><span class="label">Discount client:</span> {{ $clientDiscountLabel }}</div>
                    <div class="row"><span class="label">Valabilitate ofertă:</span> {{ $validityDays !== null ? $validityDays . ' zile' : (optional($comanda->valabilitate_oferta)->format('d.m.Y') ?? '-') }}</div>
                    <div class="row"><span class="label">Timp execuție*:</span> {{ $estimatedDays !== null ? $estimatedDays . ' zile' : (optional($comanda->timp_estimat_livrare)->format('d.m.Y H:i') ?? '-') }}</div>
                </td>
            </tr>
        </table>

        <div class="box">
            Vă mulțumim pentru interesul acordat produselor și serviciilor tipografiei blu.e-print.<br>
            Urmare a solicitării, vă transmitem oferta de preț.
        </div>
        <div class="box muted" style="margin-top: 0; padding-top: 0;">
            *estimat de la data acceptului “Bun de tipar” si achitarii integrale a comenzii
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width:5%;">nr.<br>crt.</th>
                    <th style="width:52%;">Denumire produs/serviciu</th>
                    <th style="width:8%;">Cantitate</th>
                    <th style="width:5%;">UM</th>
                    <th style="width:10%;">preț unitar [lei]</th>
                    <th style="width:10%;">Valoare [lei]</th>
                    <th style="width:10%;">T.V.A. [{{ $vatRatePercentLabel }}]</th>
                    <th style="width:10%;">TOTAL cu TVA [lei]</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $linie)
                    @php
                        $lineValue = (float) $linie->total_linie;
                        $lineVat = $lineValue * $vatRate;
                        $lineTotalWithVat = $lineValue + $lineVat;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td>
                            <strong>{{ $linie->custom_denumire ?? ($linie->produs->denumire ?? '-') }}</strong>
                            @if ($showDetails && $linie->descriere)
                                <div class="muted">{{ $linie->descriere }}</div>
                            @endif
                        </td>
                        <td class="text-right">{{ $formatQuantity($linie->cantitate) }}</td>
                        <td class="text-center">{{ $umDefault }}</td>
                        <td class="text-center">{{ number_format((float) $linie->pret_unitar, 2) }}</td>
                        <td class="text-center">{{ number_format($lineValue, 2) }}</td>
                        <td class="text-center">{{ number_format($lineVat, 2) }}</td>
                        <td class="text-center">{{ number_format($lineTotalWithVat, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">Nu există produse adăugate.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="summary-row">
                    <td colspan="6" class="summary-label">Total fără T.V.A.</td>
                    <td class="text-right">{{ number_format($subtotal, 2) }}</td>
                    <td class="text-right">{{ number_format($subtotal, 2) }}</td>
                </tr>
                <tr class="summary-row">
                    <td colspan="6" class="summary-label">T.V.A. [{{ $vatRatePercentLabel }}]</td>
                    <td class="text-right">{{ number_format($vatTotal, 2) }}</td>
                    <td class="text-right">{{ number_format($totalWithVat, 2) }}</td>
                </tr>
                <tr class="summary-row summary-total">
                    <td colspan="6" class="summary-label">TOTAL</td>
                    <td class="text-right">{{ number_format($vatTotal, 2) }}</td>
                    <td class="text-right">{{ number_format($totalWithVat, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
