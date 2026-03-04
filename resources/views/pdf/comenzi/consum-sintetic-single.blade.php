@php
    $formatQuantity = static function ($value) {
        $formatted = number_format((float) $value, 4, '.', '');
        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    };
    $rows = collect($report['summary_rows'] ?? []);
    $orderMeta = $report['order_meta'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] ?? 'Fisa sintetica comanda-consumuri' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .title { text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 18px; }
        .meta, .summary { width: 100%; border-collapse: collapse; }
        .meta { margin-bottom: 34px; }
        .meta td { padding: 4px 6px; border: 1px solid #d1d5db; }
        .meta .label { width: 28%; font-weight: bold; background: #f3f4f6; }
        .summary th, .summary td { border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
        .summary th { background: #eef2f7; text-align: left; }
        .summary .group-head th { text-align: center; font-size: 11px; }
        .summary .group-head .blank { background: #fff; }
        .summary .number { text-align: center; width: 5%; }
        .summary .rebut { background: #fff1f1; }
        .qty { font-weight: bold; }
    </style>
</head>
<body>
    <div class="title">{{ $report['title'] ?? 'FISA SINTETICA COMANDA-CONSUMURI' }}</div>

    <table class="meta">
        <tr>
            <td class="label">Nr. comanda</td>
            <td>{{ $orderMeta['order_id'] ?? '-' }}</td>
            <td class="label">Data comenzii</td>
            <td>{{ $orderMeta['order_date'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Client</td>
            <td>{{ $orderMeta['client_name'] ?? '-' }}</td>
            <td class="label">Data finalizarii comenzii</td>
            <td>{{ $orderMeta['completed_at'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Data livrarii comenzii</td>
            <td>{{ $orderMeta['delivery_at'] ?? '-' }}</td>
            <td class="label">Operator</td>
            <td>{{ $report['generated_by'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Data listarii fisei</td>
            <td colspan="3">{{ optional($report['generated_at'] ?? null)->format('d.m.Y H:i') ?? '-' }}</td>
        </tr>
    </table>

    <table class="summary">
        <thead>
            <tr class="group-head">
                <th class="blank" colspan="4"></th>
                <th>CONSUM MATERIALE</th>
                <th class="rebut">REBUTURI</th>
                <th>CONSUM+REBUT</th>
            </tr>
            <tr>
                <th class="number">#</th>
                <th>Material</th>
                <th>UM</th>
                <th>Echipament utilizat</th>
                <th>Consum</th>
                <th class="rebut">Rebut</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="number">{{ $loop->iteration }}</td>
                    <td>{{ $row['material'] }}</td>
                    <td>{{ $row['unitate_masura'] }}</td>
                    <td>{{ $row['echipamente'] }}</td>
                    <td class="qty">{{ $formatQuantity($row['consum']) }}</td>
                    <td class="rebut qty">{{ $formatQuantity($row['rebut']) }}</td>
                    <td class="qty">{{ $formatQuantity($row['total']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Nu exista materiale de centralizat.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
