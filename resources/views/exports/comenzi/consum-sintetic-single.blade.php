@php
    $formatQuantity = static function ($value) {
        $formatted = number_format((float) $value, 4, '.', '');
        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    };
    $rows = collect($report['summary_rows'] ?? []);
    $orderMeta = $report['order_meta'] ?? [];
@endphp
<table border="0">
    <tr>
        <td colspan="7" style="font-size: 18px; font-weight: bold;">{{ $report['title'] ?? 'FISA SINTETICA COMANDA-CONSUMURI' }}</td>
    </tr>
    <tr><td colspan="7"></td></tr>
    <tr>
        <td style="font-weight: bold;">Nr. comanda</td>
        <td>{{ $orderMeta['order_id'] ?? '-' }}</td>
        <td style="font-weight: bold;">Data comenzii</td>
        <td>{{ $orderMeta['order_date'] ?? '-' }}</td>
        <td style="font-weight: bold;">Client</td>
        <td colspan="2">{{ $orderMeta['client_name'] ?? '-' }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Data finalizarii comenzii</td>
        <td>{{ $orderMeta['completed_at'] ?? '-' }}</td>
        <td style="font-weight: bold;">Data livrarii comenzii</td>
        <td>{{ $orderMeta['delivery_at'] ?? '-' }}</td>
        <td style="font-weight: bold;">Operator</td>
        <td colspan="2">{{ $report['generated_by'] ?? '-' }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Data listarii fisei</td>
        <td colspan="6">{{ optional($report['generated_at'] ?? null)->format('d.m.Y H:i') ?? '-' }}</td>
    </tr>
</table>

<table border="1" cellspacing="0" cellpadding="5" style="margin-top: 16px; border-collapse: collapse;">
    <thead>
        <tr style="background: #eef2f7; font-weight: bold;">
            <th>#</th>
            <th>Material</th>
            <th>UM</th>
            <th>Echipament utilizat</th>
            <th>Consum</th>
            <th style="background: #fff1f1;">Rebut</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $row['material'] }}</td>
                <td>{{ $row['unitate_masura'] }}</td>
                <td>{{ $row['echipamente'] }}</td>
                <td>{{ $formatQuantity($row['consum']) }}</td>
                <td style="background: #fff1f1;">{{ $formatQuantity($row['rebut']) }}</td>
                <td>{{ $formatQuantity($row['total']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align: center;">Nu exista materiale de centralizat.</td>
            </tr>
        @endforelse
    </tbody>
</table>
