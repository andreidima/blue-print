<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Fișă internă comandă #{{ $comanda->id }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #27358f;
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
            padding: 34mm 16mm 24mm;
        }
        .doc-meta-title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 1mm;
            color: #27358f;
        }
        .doc-meta-subtitle {
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            margin: 0 0 4mm;
            color: #27358f;
        }
        .page-number {
            position: absolute;
            z-index: 2;
            width: 9mm;
            height: 9mm;
            line-height: 9mm;
            text-align: center;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            left: 100.7mm;
            top: 282.3mm;
        }
        .two-col {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4mm;
        }
        .two-col td {
            vertical-align: top;
            width: 50%;
            padding: 0 4mm 0 0;
        }
        .row { margin: 0 0 1mm; }
        .label { font-weight: 700; }
        .section-title {
            margin: 4mm 0 2mm;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1mm;
            color: #111827;
        }
        table.table th,
        table.table td {
            border: 1px solid #1f1f1f;
            padding: 2px 3px;
            vertical-align: top;
        }
        table.table th {
            font-size: 11px;
            font-weight: 700;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .request-title {
            font-size: 11px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 1px;
        }
        .request-desc {
            font-size: 9px;
            line-height: 1.15;
            color: #1f2937;
            white-space: pre-line;
        }
        .muted { color: #4b5563; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
@php
    $orderNumber = str_pad((string) $comanda->id, 6, '0', STR_PAD_LEFT);
    $sheetDate = optional($comanda->data_solicitarii)->format('d/m/Y') ?? now()->format('d/m/Y');
    $sheetPrintedAt = now()->format('d.m.Y-H:i');
    $printedBy = auth()->user()?->name ?? '-';

    $billingAddress = $comanda->adresa_facturare ?? optional($comanda->client)->adresa ?? '-';
    $deliveryAddress = $comanda->adresa_livrare ?? $billingAddress;

    $gdprSigned = $comanda->gdprConsents()->exists();
    $yesNo = static fn (bool $value): string => $value ? 'DA' : 'NU';
    $formatQuantity = static function ($value): string {
        if ($value === null || $value === '') {
            return '-';
        }

        $formatted = number_format((float) $value, 4, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    };

    $noteGroups = $comanda->note->groupBy('role');
    $hasSupervisorNote = $noteGroups->has('supervizor') && $noteGroups->get('supervizor', collect())->isNotEmpty();
    $hasFrontdeskNote = $noteGroups->has('frontdesk') && $noteGroups->get('frontdesk', collect())->isNotEmpty();
    $hasGraficianNote = $noteGroups->has('grafician') && $noteGroups->get('grafician', collect())->isNotEmpty();
    $hasExecutantNote = $noteGroups->has('executant') && $noteGroups->get('executant', collect())->isNotEmpty();

    $mockupByType = $comanda->mockupuri->groupBy(fn ($item) => $item->tip ?: \App\Models\Mockup::TIP_INFO_MOCKUP);
    $hasInfoGrafica = ($mockupByType->get(\App\Models\Mockup::TIP_INFO_GRAFICA) ?? collect())->isNotEmpty();
    $hasInfoMockup = ($mockupByType->get(\App\Models\Mockup::TIP_INFO_MOCKUP) ?? collect())->isNotEmpty();
    $hasInfoTest = ($mockupByType->get(\App\Models\Mockup::TIP_INFO_TEST) ?? collect())->isNotEmpty();
    $hasInfoBunDeTipar = ($mockupByType->get(\App\Models\Mockup::TIP_INFO_BUN_DE_TIPAR) ?? collect())->isNotEmpty();

    $hasAtasamente = $comanda->atasamente->isNotEmpty();
    $hasFacturi = $comanda->facturi->isNotEmpty();

    $firstPlata = $comanda->plati->sortBy('platit_la')->first();
    $hasAvans = $firstPlata !== null;

    $etapaAssignments = $comanda->etapaAssignments
        ->filter(fn ($assignment) => $assignment->user && $assignment->etapa)
        ->sortBy(fn ($assignment) => $assignment->etapa->id ?? 0)
        ->groupBy('etapa_id');

    $statusLabel = \App\Enums\StatusComanda::options()[$comanda->status] ?? $comanda->status;
    $tipLabel = \App\Enums\TipComanda::options()[$comanda->tip] ?? $comanda->tip;
    $sursaLabel = \App\Enums\SursaComanda::options()[$comanda->sursa] ?? $comanda->sursa;

    $bgPage = \App\Support\PdfAsset::fromPublic('assets/pdf-backgrounds/fisa-interna-p1.png');
@endphp

<section class="page">
    <img class="page-bg" src="{{ $bgPage }}" alt="">
    <div class="page-number">-1-</div>
    <div class="content">
        <div class="doc-meta-title">nr. {{ $orderNumber }}</div>
        <div class="doc-meta-subtitle">Data : {{ $sheetDate }}</div>

        <table class="two-col">
            <tr>
                <td>
                    <div class="row"><span class="label">Client :</span> {{ optional($comanda->client)->nume_complet ?? '-' }}</div>
                    <div class="row"><span class="label">Telefon :</span> {{ optional($comanda->client)->telefon ?? '-' }}</div>
                    <div class="row"><span class="label">Telefon secundar :</span> {{ optional($comanda->client)->telefon_secundar ?? '-' }}</div>
                    <div class="row"><span class="label">e-mail :</span> {{ optional($comanda->client)->email ?? '-' }}</div>
                    <div class="row"><span class="label">Adresa facturare :</span> {{ $billingAddress }}</div>
                    <div class="row"><span class="label">Adresa livrare :</span> {{ $deliveryAddress }}</div>
                </td>
                <td>
                    <div class="row"><span class="label">Tip comanda :</span> {{ $tipLabel }}</div>
                    <div class="row"><span class="label">Sursă :</span> {{ $sursaLabel }}</div>
                    <div class="row"><span class="label">Status :</span> {{ $statusLabel }}</div>
                    <div class="row"><span class="label">Dată livrare :</span> {{ optional($comanda->timp_estimat_livrare)->format('d.m.Y') ?? '-' }}</div>
                    <div class="row"><span class="label">Data listării fișei :</span> {{ $sheetPrintedAt }}</div>
                    <div class="row"><span class="label">Listat de :</span> {{ $printedBy }}</div>
                    <div class="row" style="margin-top:6mm;"><span class="label">Semnătură</span> ...........................................</div>
                </td>
            </tr>
        </table>

        <div class="section-title">1 - Detalii comandă</div>
        <div class="row"><span class="label">Necesită mock-up :</span> {{ $yesNo((bool) $comanda->necesita_mockup) }}</div>
        <div class="row"><span class="label">Necesită tipar exemplu :</span> {{ $yesNo((bool) $comanda->necesita_tipar_exemplu) }}</div>
        <div class="row"><span class="label">Formular GDPR semnat :</span> {{ $yesNo($gdprSigned) }}</div>

        <div class="section-title">2 - Informații comandă</div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:6%;">nr.<br>crt.</th>
                    <th style="width:36%;">Solicitare client</th>
                    <th style="width:11%;">Cantitate</th>
                    <th style="width:6%;">UM</th>
                    <th style="width:24%;">Adăugat de</th>
                    <th style="width:17%;">Data<br>ora</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($comanda->solicitari as $solicitare)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}.</td>
                        <td>
                            <div class="request-title">{{ $solicitare->solicitare_client ?? '-' }}</div>
                            @if ($solicitare->solicitare_client)
                                <div class="request-desc">Descriere :
-{{ str_replace(["\r\n", "\n", "\r"], "\n-", trim($solicitare->solicitare_client)) }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ $formatQuantity($solicitare->cantitate) }}</td>
                        <td class="text-center">buc</td>
                        <td class="text-center">{{ optional($solicitare->createdBy)->name ?? $solicitare->created_by_label ?? '-' }}</td>
                        <td class="text-center">
                            {{ optional($solicitare->created_at)->format('d.m.Y') ?? '-' }}<br>
                            ora {{ optional($solicitare->created_at)->format('H.i') ?? '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center muted">Nu există informații adăugate.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="page">
    <img class="page-bg" src="{{ $bgPage }}" alt="">
    <div class="page-number">-2-</div>
    <div class="content">
        <div class="doc-meta-title">nr. {{ $orderNumber }}</div>
        <div class="doc-meta-subtitle">Data : {{ $sheetDate }}</div>

        <div class="section-title">3 - Note</div>
        <div class="row"><span class="label">Supervizor :</span> {{ $yesNo($hasSupervisorNote) }}</div>
        <div class="row"><span class="label">Front-desk :</span> {{ $yesNo($hasFrontdeskNote) }}</div>
        <div class="row"><span class="label">Grafician :</span> {{ $yesNo($hasGraficianNote) }}</div>
        <div class="row"><span class="label">Executant(operator) :</span> {{ $yesNo($hasExecutantNote) }}</div>

        <div class="section-title">4 - Necesar : {{ $comanda->produse->isNotEmpty() ? 'DA' : '-' }}</div>

        <div class="section-title">5 - Note</div>
        <div class="row"><span class="label">Atașamente :</span> {{ $hasAtasamente ? 'DA' : 'nu exista atasamente' }}</div>
        <div class="row"><span class="label">Facturi :</span> {{ $yesNo($hasFacturi) }}</div>
        <div class="row"><span class="label">Info grafică :</span> {{ $yesNo($hasInfoGrafica) }}</div>
        <div class="row"><span class="label">Info mock-up :</span> {{ $yesNo($hasInfoMockup) }}</div>
        <div class="row"><span class="label">Info test :</span> {{ $yesNo($hasInfoTest) }}</div>
        <div class="row"><span class="label">Info “BUN DE TIPAR” :</span> {{ $yesNo($hasInfoBunDeTipar) }}</div>

        <div class="section-title">5 - Plăți</div>
        <div class="row"><span class="label">Avans :</span> {{ $yesNo($hasAvans) }}</div>
        <div class="row"><span class="label">Data avans :</span> {{ $firstPlata ? optional($firstPlata->platit_la)->format('d.m.Y-H.i') : '-' }}</div>

        <div class="section-title">6 - Etape comandă(Responsabili etape)</div>
        @forelse ($etapaAssignments as $assignments)
            @php
                $etapa = $assignments->first()->etapa;
            @endphp
            <div class="row" style="margin-bottom:0;"><span class="label">{{ $etapa->label ?? 'Etapă' }} :</span></div>
            @foreach ($assignments as $assignment)
                @php
                    $userName = optional($assignment->user)->name ?? '-';
                    $roleName = optional(optional($assignment->user)->primaryActiveRole())->name;
                    $statusValue = $assignment->status === 'approved'
                        ? (optional($assignment->updated_at)->format('d.m.Y-H.i') ?? 'aprobat')
                        : 'pending';
                @endphp
                <div class="row">-{{ $userName }}@if($roleName)({{ strtolower($roleName) }})@endif : {{ $statusValue }}</div>
            @endforeach
        @empty
            <div class="row muted">Nu există etape asignate.</div>
        @endforelse
    </div>
</section>
</body>
</html>
