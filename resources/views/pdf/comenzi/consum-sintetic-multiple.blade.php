@php
    $formatQuantity = static function ($value) {
        $formatted = number_format((float) $value, 4, '.', '');
        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    };
    $detailGroups = collect($report['detail_rows'] ?? [])->groupBy('product_group_key')->values();
    $summaryRows = collect($report['summary_rows'] ?? []);
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] ?? 'Fisa sintetica comenzi-consumuri' }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
        }
        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .subtitle {
            text-align: center;
            font-size: 12px;
            margin-bottom: 18px;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 26px;
        }
        .meta td {
            padding: 4px 6px;
            border: 1px solid #d1d5db;
            text-align: left;
        }
        .meta .label {
            width: 28%;
            font-weight: bold;
            background: #f3f4f6;
        }
        .section-title {
            font-weight: bold;
            margin-bottom: 4px;
        }
        .detail-table,
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .detail-table {
            page-break-inside: auto;
        }
        .detail-head th,
        .summary-head th {
            background: #eef2f7;
            text-align: left;
            font-weight: bold;
            padding: 6px 6px;
            border: 1px solid #d1d5db;
        }
        .detail-head .group-row th,
        .summary-head .group-row th {
            text-align: center;
            font-size: 10px;
        }
        .detail-head .group-row .blank,
        .summary-head .group-row .blank {
            background: #fff;
        }
        .detail-table td,
        .summary-table td {
            padding: 5px 6px;
            vertical-align: top;
        }
        .detail-table tr,
        .summary-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        .qty {
            font-weight: bold;
        }
        .rebut {
            background: #fff1f1;
        }
        .block-group {
            page-break-inside: avoid;
        }
        .block-top td {
            border-top: 1px solid #111827;
        }
        .block-bottom td {
            border-bottom: 1px solid #111827;
        }
        .block-row td:first-child {
            border-left: 1px solid #111827;
        }
        .block-row td:last-child {
            border-right: 1px solid #111827;
        }
        .summary-wrap {
            border: 1px solid #111827;
            padding: 0;
        }
        .summary-table td,
        .summary-table th {
            border: none;
        }
        .spacer {
            height: 24px;
        }
        .w-product { width: 20%; }
        .w-product-qty { width: 7%; }
        .w-material { width: 19%; }
        .w-um { width: 7%; }
        .w-consum { width: 9%; }
        .w-eq { width: 12%; }
        .w-datetime { width: 9%; }
        .w-user { width: 8%; }
        .w-rebut { width: 7%; }
        .w-total { width: 8%; }
    </style>
</head>
<body>
    <div class="title">{{ $report['title'] ?? 'FISA SINTETICA COMENZI-CONSUMURI' }}</div>
    <div class="subtitle">PERIOADA : {{ $report['period_label'] ?? '-' }}</div>

    <table class="meta">
        <tr>
            <td class="label">Nr. de comenzi</td>
            <td>{{ $report['order_count'] ?? 0 }}</td>
            <td class="label">Nr. clienti</td>
            <td>{{ $report['client_count'] ?? 0 }}</td>
        </tr>
        <tr>
            <td class="label">Clienti noi</td>
            <td>{{ $report['new_client_count'] ?? 0 }}</td>
            <td class="label">Operator</td>
            <td>{{ $report['generated_by'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Data listarii fisei</td>
            <td colspan="3">{{ optional($report['generated_at'] ?? null)->format('d.m.Y H:i') ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">CONSUM MATERIALE</div>
    <table class="detail-table">
        <thead class="detail-head">
            <tr class="group-row">
                <th class="blank" colspan="2"></th>
                <th colspan="3">CONSUM MATERIALE</th>
                <th class="blank" colspan="3"></th>
                <th class="rebut">REBUTURI</th>
                <th>CONSUM+REBUT</th>
            </tr>
            <tr>
                <th class="w-product">Produs</th>
                <th class="w-product-qty">Cantitate</th>
                <th class="w-material">Denumire material primar</th>
                <th class="w-um">UM</th>
                <th class="w-consum">Consum</th>
                <th class="w-eq">Echipament utilizat</th>
                <th class="w-datetime">Data/ora completarii</th>
                <th class="w-user">Utilizator</th>
                <th class="w-rebut rebut">Cantitate</th>
                <th class="w-total">Cantitate</th>
            </tr>
        </thead>
        @forelse ($detailGroups as $group)
            <tbody class="block-group">
                @foreach ($group as $row)
                    <tr class="block-row {{ $loop->first ? 'block-top' : '' }} {{ $loop->last ? 'block-bottom' : '' }}">
                        <td>{{ $loop->first ? ($row['product'] ?? '-') : '' }}</td>
                        <td class="qty">{{ $loop->first ? $formatQuantity($row['product_quantity'] ?? 0) : '' }}</td>
                        <td>{{ $row['material'] ?? '-' }}</td>
                        <td>{{ $row['unitate_masura'] ?? '' }}</td>
                        <td class="qty">{{ $formatQuantity($row['consum'] ?? 0) }}</td>
                        <td>{{ ($row['equipment'] ?? '-') !== '-' ? $row['equipment'] : '' }}</td>
                        <td>{{ $row['recorded_at'] ?? '-' }}</td>
                        <td>{{ $row['recorded_by'] ?? '-' }}</td>
                        <td class="qty rebut">{{ $formatQuantity($row['rebut'] ?? 0) }}</td>
                        <td class="qty">{{ $formatQuantity($row['total'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        @empty
            <tbody class="block-group">
                <tr class="block-row block-top block-bottom">
                    <td colspan="10">Nu exista consumuri pentru comenzile selectate.</td>
                </tr>
            </tbody>
        @endforelse
    </table>

    <div class="spacer"></div>
    <div class="spacer"></div>

    <div class="section-title">Centralizator CONSUM</div>
    <div class="summary-wrap">
        <table class="summary-table">
            <thead class="summary-head">
                <tr class="group-row">
                    <th class="blank" colspan="2"></th>
                    <th colspan="3">CONSUM MATERIALE</th>
                    <th class="blank" colspan="3"></th>
                    <th class="rebut">REBUTURI</th>
                    <th>CONSUM+REBUT</th>
                </tr>
                <tr>
                    <th class="w-product">Produs</th>
                    <th class="w-product-qty">Cantitate</th>
                    <th class="w-material">Denumire material primar</th>
                    <th class="w-um">UM</th>
                    <th class="w-consum">Consum</th>
                    <th class="w-eq">Echipament utilizat</th>
                    <th class="w-datetime">Data/ora completarii</th>
                    <th class="w-user">Utilizator</th>
                    <th class="w-rebut rebut">Cantitate</th>
                    <th class="w-total">Cantitate</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summaryRows as $row)
                    <tr>
                        <td>{{ $loop->first ? 'TOTAL' : '' }}</td>
                        <td></td>
                        <td>{{ $row['material'] ?? '-' }}</td>
                        <td>{{ $row['unitate_masura'] ?? '' }}</td>
                        <td class="qty">{{ $formatQuantity($row['consum'] ?? 0) }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="qty rebut">{{ $formatQuantity($row['rebut'] ?? 0) }}</td>
                        <td class="qty">{{ $formatQuantity($row['total'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>TOTAL</td>
                        <td></td>
                        <td>Nu exista materiale de centralizat.</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="rebut"></td>
                        <td></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
