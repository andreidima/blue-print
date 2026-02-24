<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Fisa interna comanda #{{ $comanda->id }}</title>
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
        .box {
            border: 1px solid #999;
            padding: 6px;
            margin-top: 6px;
        }
        .check {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1px solid #555;
            text-align: center;
            line-height: 13px;
            margin-right: 4px;
            font-size: 10px;
        }
        .clearfix::after {
            content: "";
            display: block;
            clear: both;
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
    $sheetDate = optional($comanda->data_solicitarii)->format('d/m/Y') ?? now()->format('d/m/Y');
    $billingAddress = $comanda->adresa_facturare ?? optional($comanda->client)->adresa ?? '-';
    $deliveryAddress = $comanda->adresa_livrare ?? $billingAddress;
    $gdprSigned = $comanda->gdprConsents()->exists();
    $etapaAssignments = $comanda->etapaAssignments
        ->filter(fn ($assignment) => $assignment->user && $assignment->etapa)
        ->sortBy(fn ($assignment) => $assignment->etapa->id ?? 0)
        ->groupBy('etapa_id');
    $totalPlatit = (float) $comanda->total_platit;
    $restPlata = (float) $comanda->total - $totalPlatit;

    $toFileUrl = static function (string $path): string {
        return 'file:///' . ltrim(str_replace('\\', '/', $path), '/');
    };

    $bgPage = $toFileUrl(public_path('assets/pdf-backgrounds/fisa-interna-p1.png'));
@endphp

<section class="page">
    <img class="page-bg" src="{{ $bgPage }}" alt="">
    <div class="content">
        <div class="clearfix">
            <div style="float:left;">
                <div class="title">Fisa interna comanda</div>
            </div>
            <div style="float:right; text-align:right;">
                <div><span class="label">Nr.</span> {{ $orderNumber }}</div>
                <div><span class="label">Data:</span> {{ $sheetDate }}</div>
            </div>
        </div>

        <div class="box">
            <div class="row"><span class="label">Client:</span> {{ optional($comanda->client)->nume_complet ?? '-' }}</div>
            <div class="row"><span class="label">Telefon:</span> {{ optional($comanda->client)->telefon ?? '-' }}</div>
            <div class="row"><span class="label">E-mail:</span> {{ optional($comanda->client)->email ?? '-' }}</div>
            <div class="row"><span class="label">Adresa facturare:</span> {{ $billingAddress }}</div>
            <div class="row"><span class="label">Adresa livrare:</span> {{ $deliveryAddress }}</div>
            <div class="row"><span class="label">Status:</span> {{ \App\Enums\StatusComanda::options()[$comanda->status] ?? $comanda->status }}</div>
            <div class="row"><span class="label">Data livrare:</span> {{ optional($comanda->timp_estimat_livrare)->format('d.m.Y H:i') ?? '-' }}</div>
        </div>

        <div class="box">
            <div class="label">1 - Detalii comanda</div>
            <div class="row"><span class="check">{{ $comanda->necesita_mockup ? 'X' : '' }}</span> Necesita mock-up</div>
            <div class="row"><span class="check">{{ $comanda->necesita_tipar_exemplu ? 'X' : '' }}</span> Necesita tipar exemplu</div>
            <div class="row"><span class="check">{{ $gdprSigned ? 'X' : '' }}</span> Formular GDPR semnat</div>
        </div>

        <div class="box">
            <div class="label">2 - Informatii comanda</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:6%;">Nr.</th>
                        <th style="width:48%;">Solicitare client</th>
                        <th style="width:10%;">Cant.</th>
                        <th style="width:16%;">Data/ora</th>
                        <th style="width:20%;">Adaugat de</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($comanda->solicitari as $solicitare)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $solicitare->solicitare_client ?? '-' }}</td>
                            <td class="text-right">{{ $solicitare->cantitate ?? '-' }}</td>
                            <td>{{ optional($solicitare->created_at)->format('d.m.Y H:i') ?? '-' }}</td>
                            <td>{{ optional($solicitare->createdBy)->name ?? $solicitare->created_by_label ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">Nu exista informatii adaugate.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="page">
    <img class="page-bg" src="{{ $bgPage }}" alt="">
    <div class="content">
        <div class="title" style="font-size:14px;">Fisa interna - flux intern / productie</div>

        <div class="box">
            <div class="label">3 - Flux de productie</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:28%;">Etapa</th>
                        <th style="width:24%;">Responsabil</th>
                        <th style="width:24%;">Status</th>
                        <th style="width:24%;">Observatii</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($etapaAssignments as $assignments)
                        @php
                            $etapa = $assignments->first()->etapa;
                            $userNames = $assignments
                                ->map(fn ($assignment) => optional($assignment->user)->name)
                                ->filter()
                                ->unique()
                                ->values()
                                ->implode(', ');
                            $statusLabels = $assignments
                                ->map(fn ($assignment) => $assignment->status === 'approved' ? 'aprobat' : 'in asteptare')
                                ->unique()
                                ->implode(', ');
                        @endphp
                        <tr>
                            <td>{{ $etapa->label ?? 'Etapa' }}</td>
                            <td>{{ $userNames !== '' ? $userNames : '-' }}</td>
                            <td>{{ $statusLabels !== '' ? $statusLabels : '-' }}</td>
                            <td></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">Nu exista etape asignate.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="box">
            <div class="label">4 - Plata / status</div>
            <div class="row"><span class="label">Total comanda:</span> {{ number_format((float) $comanda->total, 2) }} lei</div>
            <div class="row"><span class="label">Total incasat:</span> {{ number_format($totalPlatit, 2) }} lei</div>
            <div class="row"><span class="label">Rest de plata:</span> {{ number_format($restPlata, 2) }} lei</div>
            <div class="row"><span class="label">Status plata:</span> {{ \App\Enums\StatusPlata::options()[$comanda->status_plata] ?? $comanda->status_plata }}</div>
        </div>

        <table class="signature-grid">
            <tr>
                <td><div class="signature-box">Operator / Responsabil comanda</div></td>
                <td><div class="signature-box">Manager / Verificare</div></td>
            </tr>
        </table>
    </div>
</section>
</body>
</html>
